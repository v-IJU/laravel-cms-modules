<?php

namespace cms\core\module\Console\Commands;

use Illuminate\Console\Command;
use Module;
use Cms;
use cms\core\module\Models\ModuleModel;
class ModuleUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:cms-module';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update modules';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Module::registerModule();

        // ── Remove deleted modules from DB ────────────────────
        $this->removeDeletedModules();

        $this->info('✅ Modules updated successfully!');
    }

    protected function removeDeletedModules(): void
    {
        $dbModules = ModuleModel::all();
        $diskModules = collect(Cms::allModules())->pluck('name')->toArray();

        foreach ($dbModules as $module) {
            if (!in_array($module->name, $diskModules)) {
                $module->delete();
                $this->info("Removed deleted module: {$module->name}");
            }
        }
    }
}
