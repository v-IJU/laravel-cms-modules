<?php

namespace cms\core\module\Console\Commands;

use Illuminate\Console\Command;
use Module;
use Cms;
use cms\core\module\Models\ModuleModel;

class ModuleUpdate extends Command
{
    protected $signature = 'update:cms-module
                            {--modules=* : Only register specific modules}
                            {--scope=    : central, tenant, or all (auto-detected)}';

    protected $description = 'Update modules';

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
            $this->info('Registering modules: ' . implode(', ', $onlyModules));
          
            Module::registerModules($onlyModules);
        } else {
            if ($scope === 'all') {
                $this->info('Registering all modules...');
                Module::registerModule();
                $this->removeDeletedModules();
            } else {
                $this->info("Registering modules for scope: [{$scope}]...");
                Module::registerModuleByScope($scope);
                $this->removeDeletedModulesByScope($scope);
               
            }
        }

        $this->info('✅ Modules updated successfully!');
    }

    protected function detectScope(): string
    {
        if (
            config('cms.tenancy_enabled') &&
            function_exists('tenancy') &&
            tenancy()->initialized
        ) {
            return 'tenant';
        }

        if (config('cms.tenancy_enabled')) {
            return 'central';
        }

        return 'all';
    }

    protected function removeDeletedModules(): void
    {
        $dbModules   = ModuleModel::all();
        $diskModules = collect(Cms::allModules())->pluck('name')->toArray();

        foreach ($dbModules as $module) {
            if (!in_array($module->name, $diskModules)) {
                $module->delete();
                $this->info("🗑️  Removed: {$module->name}");
            }
        }
    }

    protected function removeDeletedModulesByScope(string $scope): void
    {
        
        $dbModules   = ModuleModel::all();
        $diskModules = collect(Cms::allModules())
            ->filter(function ($module) use ($scope) {
                $jsonFile = base_path(
                    str_replace('\\', '/', ltrim($module['path'] ?? '', '/'))
                    . '/module.json'
                );
                if (!file_exists($jsonFile)) return true;
                $config  = json_decode(file_get_contents($jsonFile), true);
                $dbScope = $config['db_scope'] ?? 'both';
                return match($scope) {
                    'central' => in_array($dbScope, ['central', 'both']),
                    'tenant'  => in_array($dbScope, ['tenant', 'both']),
                    default   => true,
                };
            })
            ->pluck('name')
            ->toArray();

        foreach ($dbModules as $module) {
            if (!in_array($module->name, $diskModules)) {
                $module->delete();
                $this->info("🗑️  Removed: {$module->name}");
            }
        }
    }
}