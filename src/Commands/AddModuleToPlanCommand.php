<?php

namespace Ramesh\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class AddModuleToPlanCommand extends Command
{
    protected $signature = 'cms:add-module-to-plan
                            {plan   : Plan slug or ID}
                            {module : Module name}
                            {--migrate : Also run migrations in tenant DBs}
                            {--force   : Skip confirmation}';

    protected $description = 'Add a module to a plan and sync all affected tenants';

    public function handle(): void
    {
        if (!config('cms.tenancy_enabled')) {
            $this->error('Tenancy not enabled!');
            return;
        }

        $planInput    = $this->argument('plan');
        $moduleName   = $this->argument('module');

        // ── Find plan ──────────────────────────────────────
        $plan = DB::connection('central')
            ->table('plans')
            ->where('id', $planInput)
            ->orWhere('slug', $planInput)
            ->first();

        if (!$plan) {
            $this->error("Plan [{$planInput}] not found!");
            return;
        }

        $this->line('');
        $this->table(['Field', 'Value'], [
            ['Plan',   $plan->name],
            ['Module', $moduleName],
        ]);

        // ── Check if already exists ────────────────────────
        $exists = DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $plan->id)
            ->where('feature_key', 'module_' . $moduleName)
            ->exists();

        if ($exists) {
            // Update to true
            DB::connection('central')
                ->table('plan_features')
                ->where('plan_id', $plan->id)
                ->where('feature_key', 'module_' . $moduleName)
                ->update(['feature_value' => 'true', 'updated_at' => now()]);
            $this->info("Updated module [{$moduleName}] to enabled for plan [{$plan->name}]");
        } else {
            // Insert new feature
            DB::connection('central')->table('plan_features')->insert([
                'plan_id'       => $plan->id,
                'feature_key'   => 'module_' . $moduleName,
                'feature_value' => 'true',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $this->info("Added module [{$moduleName}] to plan [{$plan->name}]");
        }

        // ── Find all tenants on this plan ──────────────────
        $tenantIds = DB::connection('central')
            ->table('tenant_plans')
            ->where('plan_id', $plan->id)
            ->whereIn('status', ['active', 'trial'])
            ->pluck('tenant_id');

        $tenants = Tenant::whereIn('id', $tenantIds)->get();

        $this->info("Found {$tenants->count()} tenant(s) on plan [{$plan->name}]");

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found for this plan.');
            return;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Sync module [{$moduleName}] to {$tenants->count()} tenant(s)?")) {
                $this->warn('Feature added to plan but tenants NOT synced.');
                return;
            }
        }

        // ── Sync each tenant ───────────────────────────────
        $success = 0;
        $failed  = 0;

        foreach ($tenants as $tenant) {
            $this->line('');
            $this->info("→ Syncing tenant: [{$tenant->id}]");

            try {
                if (tenancy()->initialized) tenancy()->end();
                tenancy()->initialize(Tenant::find($tenant->id));

                // ── Run migration if needed ────────────────
                if ($this->option('migrate')) {
                    $this->info("  Running migration for [{$moduleName}]...");
                    \Artisan::call('cms-migrate', [
                        '--module'         => $moduleName,
                        '--no-interaction' => true,
                    ]);
                    $this->line(\Artisan::output());

                    tenancy()->end();
                    tenancy()->initialize(Tenant::find($tenant->id));
                }

                // ── Register module ────────────────────────
                \Artisan::call('update:cms-module', [
                    '--modules'        => [$moduleName],
                    '--no-interaction' => true,
                ]);

                tenancy()->end();
                tenancy()->initialize(Tenant::find($tenant->id));

                // ── Register menu ──────────────────────────
                \Artisan::call('update:cms-menu', [
                    '--modules'        => [$moduleName],
                    '--no-interaction' => true,
                ]);

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
        $this->line('  ╔══════════════════════════════════════════╗');
        $this->line('  ║     ✅ Module sync complete!              ║');
        $this->line('  ╚══════════════════════════════════════════╝');
        $this->line('');
        $this->info("  Plan    : {$plan->name}");
        $this->info("  Module  : {$moduleName}");
        $this->info("  Success : {$success}");
        $this->info("  Failed  : {$failed}");
        $this->line('');
    }
}
