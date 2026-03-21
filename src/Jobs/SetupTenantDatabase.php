<?php

namespace Ramesh\Cms\Jobs;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SetupTenantDatabase
{
    /**
     * Tenant passed via constructor by JobPipeline
     * $this->passable = [$tenant] → new SetupTenantDatabase($tenant)
     */
    public function __construct(protected $tenant) {}

    /**
     * No arguments — tenant available via $this->tenant
     */
    public function handle(): void
    {
        $tenant = $this->tenant;

        Log::info("[CMS] Setting up tenant: {$tenant->id}");

        try {
            $this->migrateTenantTables();

            $allowedModules = $this->getAllowedModules($tenant);
            Log::info("[CMS] Allowed: " . implode(', ', $allowedModules));

            $this->registerModules($allowedModules);
            $this->registerMenus($allowedModules);
            $this->seedDefaults($tenant, $allowedModules);

            Log::info("[CMS] Tenant [{$tenant->id}] setup complete!");
        } catch (\Exception $e) {
            Log::error("[CMS] Failed: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    protected function migrateTenantTables(): void
    {
        Artisan::call('cms-migrate', [
            '--db'             => 'tenant',
            '--no-interaction' => true,
        ]);
        Log::info('[CMS] Migrated: ' . Artisan::output());
    }

    protected function getAllowedModules($tenant): array
    {
        $planId = $tenant->plan_id ?? null;

        if ($planId) {
            Log::info("[CMS] plan_id: {$planId}");
            return $this->getModulesByPlanId((int) $planId);
        }

        $plan = DB::connection('central')
            ->table('tenant_plans')
            ->join('plans', 'plans.id', '=', 'tenant_plans.plan_id')
            ->where('tenant_plans.tenant_id', $tenant->id)
            ->where('tenant_plans.status', 'active')
            ->select('plans.*')
            ->first();

        if (!$plan) {
            Log::warning("[CMS] No plan — defaults");
            return $this->getDefaultModules();
        }

        return $this->getModulesByPlanId($plan->id);
    }

    protected function getModulesByPlanId(int $planId): array
    {
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

        return empty($modules) ? $this->getDefaultModules() : $modules;
    }

    protected function getDefaultModules(): array
    {
        return ['user', 'menu', 'configurations'];
    }

    protected function registerModules(array $allowedModules): void
    {
        if ($allowedModules === ['*']) {
            Artisan::call('update:cms-module', ['--no-interaction' => true]);
        } else {
            Artisan::call('update:cms-module', [
                '--modules'        => $allowedModules,
                '--no-interaction' => true,
            ]);
        }
        Log::info('[CMS] Modules: ' . Artisan::output());
    }

    protected function registerMenus(array $allowedModules): void
    {
        if ($allowedModules === ['*']) {
            Artisan::call('update:cms-menu', ['--no-interaction' => true]);
        } else {
            Artisan::call('update:cms-menu', [
                '--modules'        => $allowedModules,
                '--no-interaction' => true,
            ]);
        }
        Log::info('[CMS] Menus: ' . Artisan::output());
    }

    protected function seedDefaults($tenant, array $allowedModules): void
    {
        // ── User groups ────────────────────────────────────
        DB::table('user_groups')->insert([
            [
                'group'  => 'Super Admin',
                'status' => 1,
            ],
            [
                'group'  => 'Staff',
                'status' => 1,
            ],
        ]);

        $adminGroupId = DB::table('user_groups')
            ->where('group', 'Super Admin')
            ->value('id');

        // ── Admin user ─────────────────────────────────────
        $adminUserId = DB::table('users')->insertGetId([
            'name'       => 'Admin',
            'username'   => 'admin',
            'email'      => 'admin@' . $tenant->id . '.com',
            'password'   => Hash::make('admin123'),
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── Map admin user to group ────────────────────────
        DB::table('user_group_map')->insert([
            'user_id'  => $adminUserId,
            'group_id' => $adminGroupId,
        ]);

        // ── Default configurations ─────────────────────────
        // Uses configurations table with name + parm columns
        DB::table('configurations')->insert([
            [
                'name' => 'site',
                'parm' => json_encode([
                    'site_name'  => $tenant->name ?? $tenant->id,
                    'theme'      => 'theme1',
                    'timezone'   => 'UTC',
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

        Log::info('[CMS] Defaults seeded for: ' . $tenant->id);
    }
}
