<?php

namespace Ramesh\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateTenantCommand extends Command
{
    protected $signature   = 'cms:create-tenant';
    protected $description = 'Create a new tenant interactively';

    public function handle(): void
    {
        if (!config('cms.tenancy_enabled')) {
            $this->error('Tenancy not enabled! Run: php artisan cms:setup-tenancy');
            return;
        }

        $this->info('Creating new tenant...');
        $this->line('');

        // ── Collect info ───────────────────────────────────
        $id    = $this->ask('Tenant ID (slug, e.g. acme)');
        $name  = $this->ask('Tenant name');
        $email = $this->ask('Admin email');

        $planId = $this->selectPlan();

        $appDomain    = env('APP_DOMAIN', 'localhost');
        $subdomain    = "{$id}.{$appDomain}";
        $customDomain = null;

        $this->info("Auto subdomain: {$subdomain}");

        if ($this->confirm('Add custom domain too?', false)) {
            $customDomain = $this->ask('Custom domain (e.g. acme.com)');
        }

        // ── Confirm ────────────────────────────────────────
        $this->line('');
        $rows = [
            ['Tenant ID', $id],
            ['Name',      $name],
            ['Email',     $email],
            ['Plan ID',   $planId],
            ['Subdomain', $subdomain],
        ];

        if ($customDomain) {
            $rows[] = ['Custom domain', $customDomain];
        }

        $this->table(['Field', 'Value'], $rows);

        if (!$this->confirm('Create this tenant?')) {
            $this->warn('Cancelled.');
            return;
        }

        try {
            // ── Step 1: Create tenant + assign plan ───────────
            // All on CENTRAL DB
            $this->info('[1/6] Creating tenant record...');

            $tenant = \App\Models\Tenant::create([
                'id'      => $id,
                'name'    => $name,
                'email'   => $email,
                'plan_id' => $planId,
                'status'  => 'active',
            ]);

            // TenantCreated fires → CreateDatabase job runs → tenant_xxx DB created
            $this->info("✅ Tenant DB created: tenant_{$id}");

            // ── Step 2: Assign plan on CENTRAL DB ─────────────
            $this->info('[2/6] Assigning plan...');
            $plan     = DB::table('plans')->find($planId);
            $startsAt = now();
            $endsAt   = match ($plan->billing_cycle ?? 'monthly') {
                'monthly'  => now()->addMonth(),
                'yearly'   => now()->addYear(),
                'weekly'   => now()->addWeek(),
                'lifetime' => null,
                default    => now()->addMonth(),
            };

            DB::table('tenant_plans')->insert([
                'tenant_id'     => $id,
                'plan_id'       => $planId,
                'status'        => 'active',
                'starts_at'     => $startsAt,
                'ends_at'       => $endsAt,
                'trial_ends_at' => isset($plan->trial_days) && $plan->trial_days > 0
                    ? now()->addDays($plan->trial_days)
                    : null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $this->info("✅ Plan assigned!");

            // ── Step 3: Add domains on CENTRAL DB ─────────────
            $this->info('[3/6] Adding domains...');
            $tenant->domains()->create(['domain' => $subdomain]);
            $this->info("✅ Subdomain: {$subdomain}");

            if ($customDomain) {
                $tenant->domains()->create(['domain' => $customDomain]);
                $this->info("✅ Custom domain: {$customDomain}");
            }

            // ── Step 4: Switch to TENANT DB ───────────────────
            $this->info('[4/6] Switching to tenant context...');
            tenancy()->initialize($tenant);
            $this->info("✅ Connected to: " . DB::connection()->getDatabaseName());

            // ── Step 5: Migrate tenant tables ─────────────────
            $this->info('[5/6] Migrating tenant tables...');
            \Artisan::call('cms-migrate', [
                '--db'             => 'tenant',
                '--no-interaction' => true,
            ]);
            $this->info(\Artisan::output());

            // ── Step 6: Register modules + menus + seed ───────
            $this->info('[6/6] Setting up tenant data...');

            $allowedModules = $this->getAllowedModules($tenant, $planId);
            $this->info("Allowed modules: " . implode(', ', $allowedModules));

            // Register modules
            if ($allowedModules === ['*']) {
                \Artisan::call('update:cms-module', ['--no-interaction' => true]);
            } else {
                \Artisan::call('update:cms-module', [
                    '--modules'        => $allowedModules,
                    '--no-interaction' => true,
                ]);
            }

            // Register menus
            if ($allowedModules === ['*']) {
                \Artisan::call('update:cms-menu', ['--no-interaction' => true]);
            } else {
                \Artisan::call('update:cms-menu', [
                    '--modules'        => $allowedModules,
                    '--no-interaction' => true,
                ]);
            }

            // Seed defaults
            $this->seedTenantDefaults($tenant, $allowedModules);

            // ── End tenant context ─────────────────────────────
            tenancy()->end();
            $this->info("✅ Back to central DB");

            // ── Show success ───────────────────────────────────
            $this->line('');
            $this->line('  ╔══════════════════════════════════════════╗');
            $this->line('  ║     ✅ Tenant Created Successfully!       ║');
            $this->line('  ╚══════════════════════════════════════════╝');
            $this->line('');
            $this->info("  Tenant ID  : {$id}");
            $this->info("  Database   : tenant_{$id}");
            $this->info("  Subdomain  : http://{$subdomain}:8100/administrator");

            if ($customDomain) {
                $this->info("  Custom     : http://{$customDomain}/administrator");
                $this->warn("  CNAME: {$customDomain} → {$appDomain}");
            }

            $this->line('');
            $this->info("  Login      : admin");
            $this->info("  Password   : admin123");
            $this->line('');
        } catch (\Exception $e) {
            tenancy()->end(); // always end tenancy on error
            $this->error('Failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }

    protected function getAllowedModules($tenant, int $planId): array
    {
        // Check wildcard
        $hasAll = DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $planId)
            ->where('feature_key', 'module_all')
            ->where('feature_value', 'true')
            ->exists();

        if ($hasAll) return ['*'];

        $modules = DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $planId)
            ->where('feature_value', 'true')
            ->where('feature_key', 'like', 'module_%')
            ->pluck('feature_key')
            ->map(fn($key) => str_replace('module_', '', $key))
            ->values()
            ->toArray();

        return empty($modules) ? ['user', 'menu', 'configurations'] : $modules;
    }

    protected function seedTenantDefaults($tenant, array $allowedModules): void
    {
        // ── User groups ────────────────────────────────────
        DB::table('user_groups')->insert([
            ['group' => 'Super Admin', 'status' => 1],
            ['group' => 'Staff',       'status' => 1],
        ]);

        $adminGroupId = DB::table('user_groups')
            ->where('group', 'Super Admin')
            ->value('id');

        // ── Admin user ─────────────────────────────────────
        $adminUserId = DB::table('users')->insertGetId([
            'name'       => 'Admin',
            'username'   => 'admin',
            'email'      => 'admin@' . $tenant->id . '.com',
            'password'   => \Hash::make('admin123'),
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── Map user to group ──────────────────────────────
        DB::table('user_group_map')->insert([
            'user_id'  => $adminUserId,
            'group_id' => $adminGroupId,
        ]);

        // ── Configurations ─────────────────────────────────
        DB::table('configurations')->insert([
            [
                'name' => 'site',
                'parm' => json_encode([
                    'site_name'   => $tenant->name ?? $tenant->id,
                    'theme'       => 'theme1',
                    'timezone'    => 'UTC',
                    'date_format' => 'Y-m-d',
                ]),
            ],
            [
                'name' => 'subscription',
                'parm' => json_encode([
                    'allowed_modules' => $allowedModules === ['*']
                        ? '*'
                        : implode(',', $allowedModules),
                ]),
            ],
        ]);
    }

    protected function selectPlan(): int
    {
        try {
            $plans = DB::table('plans')
                ->where('is_active', true)
                ->orderBy('order')
                ->get(['id', 'name', 'price', 'billing_cycle']);

            if ($plans->isEmpty()) {
                $this->warn('No plans found — using default (1)');
                return 1;
            }

            $choices = $plans->mapWithKeys(
                fn($p) => [$p->id => "{$p->name} (\${$p->price}/{$p->billing_cycle})"]
            )->toArray();

            $selected = $this->choice('Select plan', array_values($choices));

            return (int) array_search($selected, $choices);
        } catch (\Exception $e) {
            $this->warn('Could not load plans — using default (1)');
            return 1;
        }
    }
}
