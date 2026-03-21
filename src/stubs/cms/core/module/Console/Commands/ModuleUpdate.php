<?php

namespace cms\core\module\Console\Commands;

use Illuminate\Console\Command;
use Module;
use Cms;
use cms\core\module\Models\ModuleModel;

class ModuleUpdate extends Command
{
    protected $signature = 'update:cms-module
                            {--modules=* : Only register specific modules}';

    protected $description = 'Update modules';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $onlyModules = $this->option('modules');

        if (!empty($onlyModules)) {
            // ── Register specific modules only ─────────────
            $this->info('Registering modules: ' . implode(', ', $onlyModules));
            Module::registerModules($onlyModules);
        } else {
            // ── Register all modules (existing behavior) ───
            $this->info('Registering all modules...');
            Module::registerModule();
            $this->removeDeletedModules();
        }

        $this->info('✅ Modules updated successfully!');
    }

    protected function removeDeletedModules(): void
    {
        $dbModules   = ModuleModel::all();
        $diskModules = collect(Cms::allModules())->pluck('name')->toArray();

        foreach ($dbModules as $module) {
            if (!in_array($module->name, $diskModules)) {
                $module->delete();
                $this->info("🗑️  Removed deleted module: {$module->name}");
            }
        }
    }
}
