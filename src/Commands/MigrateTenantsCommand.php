<?php

namespace Ramesh\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class MigrateTenantsCommand extends Command
{
    protected $signature = 'cms:migrate-tenants
                            {--tenant=  : Migrate specific tenant only}
                            {--module=  : Migrate specific module only}
                            {--force    : Skip confirmation}';

    protected $description = 'Run tenant DB migrations across all tenants';

    public function handle(): void
    {
        if (!config('cms.tenancy_enabled')) {
            $this->error('Tenancy not enabled!');
            return;
        }

        $tenantId = $this->option('tenant');
        $module   = $this->option('module');

        // ── Get tenants to migrate ─────────────────────────
        if ($tenantId) {
            $tenants = Tenant::where('id', $tenantId)->get();
            if ($tenants->isEmpty()) {
                $this->error("Tenant [{$tenantId}] not found!");
                return;
            }
        } else {
            $tenants = Tenant::whereIn('onboard_status', ['active', 'trial'])
                ->get();
        }

        $this->info("Found {$tenants->count()} tenant(s) to migrate");

        if (!$this->option('force')) {
            if (!$this->confirm("Run migrations on {$tenants->count()} tenant(s)?")) {
                $this->warn('Cancelled.');
                return;
            }
        }

        // ── Run migrations per tenant ──────────────────────
        $success = 0;
        $failed  = 0;

        foreach ($tenants as $tenant) {
            $this->line('');
            $this->info("→ Migrating tenant: [{$tenant->id}]");

            try {
                // Switch to tenant DB
                if (tenancy()->initialized) tenancy()->end();
                tenancy()->initialize($tenant);

                if ($module) {
                    // Specific module only
                    \Artisan::call('cms-migrate', [
                        '--module'         => $module,
                        '--no-interaction' => true,
                    ]);
                } else {
                    // All tenant scope migrations
                    \Artisan::call('cms-migrate', [
                        '--db'             => 'tenant',
                        '--no-interaction' => true,
                    ]);
                }

                $output = \Artisan::output();
                if ($output) $this->line($output);

                $this->info("  ✅ [{$tenant->id}] migrated!");
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
}
