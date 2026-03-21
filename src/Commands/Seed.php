<?php

namespace Ramesh\Cms\Commands;

use Illuminate\Console\Command;
use Cms;
use Illuminate\Support\Facades\File;

class Seed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:cms-seed {--module=} {--class=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'cms-module database seed';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    /*
     * module name
     */
    private $module_name;
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        $module = $this->option('module');
        $class  = $this->option('class');

        // ── Specific module ────────────────────────────────
        if ($module) {
            if ($class) {
                $this->call('db:seed', [
                    '--class' => Cms::getPath() . '\\' . $module . '\\Database\\seeds\\' . $class
                ]);
            } else {
                $modulePath = base_path()
                    . '/' . Cms::getPath()
                    . '/' . Cms::getModulesPath()
                    . '/' . Cms::getCurrentTheme()
                    . '/' . $module
                    . '/Database/seeds';

                foreach ($this->getAllFileInFolder($modulePath) as $file) {
                    $className = preg_replace('/\..+$/', '', $file);
                    $this->call('db:seed', [
                        '--class' => Cms::getPath() . '\\' . $module . '\\Database\\seeds\\' . $className
                    ]);
                }
            }
            return;
        }

        // ── All modules ────────────────────────────────────
        $cms = Cms::allModulesPath(false);

        foreach ($cms as $modulePath) {
            if ($class) {
                $seedFile = base_path() . '/' . $modulePath . '/Database/seeds/' . $class . '.php';

                if (File::exists($seedFile)) {
                    $this->call('db:seed', [
                        '--class' => $modulePath . '\\Database\\seeds\\' . $class
                    ]);
                }
            } else {
                $files = $this->getAllFileInFolder(
                    base_path() . '/' . $modulePath . '/Database/seeds'
                );

                foreach ($files as $file) {
                    $className = preg_replace('/\..+$/', '', $file);
                    $m         = ltrim($modulePath, '/');
                    $m         = str_replace('/', '\\', $m);
                    $m         = str_replace('local\\' . Cms::getCurrentTheme() . '\\', '', $m);

                    $this->call('db:seed', [
                        '--class' => $m . '\\Database\\seeds\\' . $className
                    ]);
                }
            }
        }

        // ── Seed plans if tenancy enabled ──────────────────
        // Runs AFTER modules registered — reads modules table
        // if (config('cms.tenancy_enabled')) {
        //     $this->info('🌱 Seeding subscription plans...');

        //     $planSeeder = 'cms\core\subscription\Database\Seeds\PlanSeeder';

        //     if (class_exists($planSeeder)) {
        //         $this->call('db:seed', ['--class' => $planSeeder]);
        //     } else {
        //         $this->warn('PlanSeeder not found — skipping plan seeding');
        //         $this->warn('Make sure subscription module is installed');
        //     }
        // }
    }

    protected function getAllFileInFolder($folder)
    {
        $path = array();
        if (File::exists($folder)) {
            $files = File::allFiles($folder);
            foreach ($files as $file) {
                $path[] = $file->getfileName();
            }
        }
        return $path;
    }
}
