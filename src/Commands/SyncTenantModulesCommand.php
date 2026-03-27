<?php

namespace Ramesh\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class SyncTenantModulesCommand extends Command
{
    protected $signature = 'cms:sync-tenant-modules
                            {--tenant=  : Sync specific tenant only}
                            {--force    : Skip confirmation}';

    protected $description = 'Sync modules and menus for all tenants based on their plan';

    public function handle(): void
    {
        if (!config('cms.tenancy_enabled')) {
            $this->error('Tenancy not enabled!');
            return;
        }

        $tenantId = $this->option('tenant');

        // ── Get tenants ────────────────────────────────────
        if ($tenantId) {
            $tenants = Tenant::where('id', $tenantId)->get();
        } else {
            $tenants = Tenant::whereIn('onboard_status', ['active', 'trial'])->get();
        }

        $this->info("Found {$tenants->count()} tenant(s) to sync");

        if (!$this->option('force')) {
            if (!$this->confirm("Sync modules for {$tenants->count()} tenant(s)?")) {
                $this->warn('Cancelled.');
                return;
            }
        }

        $success = 0;
        $failed  = 0;

        foreach ($tenants as $tenant) {
            $this->line('');
            $this->info("→ Syncing tenant: [{$tenant->id}]");

            try {
                // ── Get allowed modules from plan ──────────
                $allowedModules = $this->getAllowedModules($tenant);
                $this->line("  Plan modules: " . implode(', ', $allowedModules));

                // ── Switch to tenant DB ────────────────────
                if (tenancy()->initialized) tenancy()->end();
                tenancy()->initialize(Tenant::find($tenant->id));

                // ── Register modules ───────────────────────
                if ($allowedModules === ['*']) {
                    \Artisan::call('update:cms-module', ['--no-interaction' => true]);
                } else {
                    \Artisan::call('update:cms-module', [
                        '--modules'        => $allowedModules,
                        '--no-interaction' => true,
                    ]);
                }

                tenancy()->end();
                tenancy()->initialize(Tenant::find($tenant->id));

                // ── Register menus ─────────────────────────
                if ($allowedModules === ['*']) {
                    \Artisan::call('update:cms-menu', ['--no-interaction' => true]);
                } else {
                    \Artisan::call('update:cms-menu', [
                        '--modules'        => $allowedModules,
                        '--no-interaction' => true,
                    ]);
                }

                $this->info("  ✅ [{$tenant->id}] synced!");
                $success++;

            } catch (\Exception $e) {
                $this->error("  ❌ [{$tenant->id}] failed: " . $e->getMessage());
                $failed++;
            } finally {
                tenancy()->end();
            }
        }

        $this->line('');
        $this->info("Done! Success: {$success} / Failed: {$failed}");
    }

    protected function getAllowedModules(Tenant $tenant): array
    {
        // Get active plan
        $plan = DB::connection('central')
            ->table('tenant_plans')
            ->join('plans', 'plans.id', '=', 'tenant_plans.plan_id')
            ->where('tenant_plans.tenant_id', $tenant->id)
            ->whereIn('tenant_plans.status', ['active', 'trial'])
            ->select('plans.*')
            ->first();

        if (!$plan) return ['user', 'menu', 'configurations'];

        // Check wildcard
        $hasAll = DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $plan->id)
            ->where('feature_key', 'module_all')
            ->where('feature_value', 'true')
            ->exists();

        if ($hasAll) return ['*'];

        $modules = DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $plan->id)
            ->where('feature_value', 'true')
            ->where('feature_key', 'like', 'module_%')
            ->pluck('feature_key')
            ->map(fn($k) => str_replace('module_', '', $k))
            ->values()
            ->toArray();

        return empty($modules) ? ['user', 'menu', 'configurations'] : $modules;
    }
}
