<?php

namespace Ramesh\Cms\Commands;

use Illuminate\Console\Command;
use Ramesh\Cms\Generators\FileGenerator;
use Cms;
class MakeModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    //protected $signature = 'make:cms-model {model-name} {module-name} {--c|controller=} {--m|migration=} {--r|resource} {-mc} {-cm} {-mcr} {-crm}';

    protected $signature = 'make:cms-model {model-name} {module-name} {--c|controller=} {--m|migration=} {--r|resource} {--t|table=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make Module Model';

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
        $module_name =  $this->argument('module-name');
        $controller_name =  $this->argument('model-name');

        $FileGenerator = new FileGenerator;
        $obj = $FileGenerator
            ->setPath(base_path().DIRECTORY_SEPARATOR.'cms'.DIRECTORY_SEPARATOR.Cms::getModulesPath().DIRECTORY_SEPARATOR.Cms::getCurrentTheme())
            ->setClass($controller_name)
            ->setModule($module_name);
        if($this->option('table')) {
            $obj = $obj->setTableName($this->option('table'));
        }
        $obj = $obj
            ->MakeModel()
            ->create();

        $this->info('model created');
    }
}
