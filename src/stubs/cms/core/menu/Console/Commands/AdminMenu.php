<?php

namespace cms\core\menu\Console\Commands;

use Illuminate\Console\Command;
use Menu;

class AdminMenu extends Command
{
    protected $signature = 'update:cms-menu
                            {--modules=* : Only register menus for specific modules}
                            {--scope=    : central, tenant, or all (auto-detected)}';

    protected $description = 'Update Admin menus';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $onlyModules = $this->option('modules');
        $scope       = $this->option('scope') ?? $this->detectScope();

        $this->info("Scope: {$scope}");

        if (!empty($onlyModules)) {
            $this->info('Registering menus for: ' . implode(', ', $onlyModules));
            Menu::registerMenusForModules($onlyModules);
        } else {
            // ── Filter by scope ────────────────────────────
            if ($scope === 'all') {
                $this->info('Registering all menus...');
                Menu::registerMenu();
            } else {
                $this->info("Registering menus for scope: [{$scope}]...");
                Menu::registerMenuByScope($scope);
            }
        }

        $this->info('✅ Menus updated successfully!');
    }

    /**
     * Auto-detect scope based on context
     */
    protected function detectScope(): string
    {
        // If tenancy is initialized → we're in tenant context
        if (
            config('cms.tenancy_enabled') &&
            function_exists('tenancy') &&
            tenancy()->initialized
        ) {
            return 'tenant';
        }

        // If tenancy enabled but NOT initialized → central context
        if (config('cms.tenancy_enabled')) {
            return 'central';
        }

        // Normal mode → all
        return 'all';
    }
}