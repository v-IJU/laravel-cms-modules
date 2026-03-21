<?php

namespace Ramesh\Cms\Commands;

use Illuminate\Console\Command;
use Cms;

class Migrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms-migrate {--module=} {--path=} {--db=all}';

    // --db=all      → normal mode (everything, single DB)
    // --db=central  → central DB only
    // --db=tenant   → tenant DB only

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Cms modules';

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
        $path   = $this->option('path');
        $db     = $this->option('db');

        // ── Specific path ──────────────────────────────────
        if ($path) {
            $this->call('migrate', ['--path' => $path]);
            return;
        }

        // ── Specific module ────────────────────────────────
        if ($module) {
            $this->call('migrate', [
                '--path' => DIRECTORY_SEPARATOR
                    . Cms::getPath()
                    . DIRECTORY_SEPARATOR
                    . Cms::getModulesPath()
                    . DIRECTORY_SEPARATOR
                    . Cms::getCurrentTheme()
                    . DIRECTORY_SEPARATOR
                    . $module
                    . DIRECTORY_SEPARATOR
                    . 'Database'
                    . DIRECTORY_SEPARATOR
                    . 'Migration'
            ]);
            return;
        }

        // ── Ask db scope if not provided ───────────────────
        if (!$db) {
            // Non-interactive mode — default to all
            if (!$this->input->isInteractive()) {
                $db = 'all';
            } else {
                $db = $this->choice(
                    'Which database do you want to migrate?',
                    [
                        'all'     => 'All modules (single DB — normal mode)',
                        'central' => 'Central DB only (tenancy mode)',
                        'tenant'  => 'Tenant DB only (tenancy mode)',
                    ],
                    'all'
                );
            }
        }


        // ── Validate db option ─────────────────────────────
        if (!in_array($db, ['all', 'central', 'tenant'])) {
            $this->error("Invalid --db option: [{$db}]. Use: all, central, tenant");
            return;
        }

        // ── Double confirmation ────────────────────────────
        // Skip confirmation in non-interactive mode
        if ($this->input->isInteractive()) {
            if (!$this->confirm("Proceed with [{$db}] migration?", true)) {
                $this->warn('Migration cancelled.');
                return;
            }
        }


        // ── Execute ────────────────────────────────────────
        match ($db) {
            'central' => $this->migrateCentral(),
            'tenant'  => $this->migrateTenant(),
            default   => $this->migrateAll(),
        };
    }

    /**
     * Normal mode — ALL core + local → single DB
     */
    protected function migrateAll(): void
    {
        $this->info('Migrating all modules (single DB)...');
        $this->copyAndMigrate($this->getAllModulePaths(), 'all');
    }

    /**
     * Tenancy — central DB
     * core modules with db_scope = central or both
     */
    protected function migrateCentral(): void
    {
        $this->info('Migrating central DB modules...');
        $this->copyAndMigrate($this->getModulesByScope('central'), 'central');
    }

    /**
     * Tenancy — tenant DB
     * core modules with db_scope = both + ALL local modules
     */
    protected function migrateTenant(): void
    {
        $this->info('Migrating tenant DB modules...');
        $this->copyAndMigrate($this->getModulesByScope('tenant'), 'tenant');
    }

    /**
     * Get ALL module paths (core + local)
     */
    protected function getAllModulePaths(): array
    {
        $paths = [];
        $cms   = Cms::allModulesPath(false);

        foreach ($cms as $module) {
            $migrationPath = base_path()
                . $module
                . DIRECTORY_SEPARATOR
                . 'Database'
                . DIRECTORY_SEPARATOR
                . 'Migration';

            if (\File::exists($migrationPath)) {
                $paths[] = $migrationPath;
            }
        }

        return $paths;
    }

    /**
     * Get module paths filtered by db_scope from module.json
     */
    protected function getModulesByScope(string $scope): array
    {
        $paths = [];

        // ── Core modules ───────────────────────────────────
        $corePath = base_path('cms/core');

        foreach (glob($corePath . '/*/module.json') as $jsonFile) {
            $config  = json_decode(file_get_contents($jsonFile), true);
            if (!$config) continue;

            $dbScope = $config['db_scope'] ?? 'both';

            $include = match ($scope) {
                'central' => in_array($dbScope, ['central', 'both']),
                'tenant'  => in_array($dbScope, ['tenant', 'both']),
                default   => true,
            };

            if ($include) {
                $migrationPath = dirname($jsonFile)
                    . DIRECTORY_SEPARATOR
                    . 'Database'
                    . DIRECTORY_SEPARATOR
                    . 'Migration';

                if (\File::exists($migrationPath)) {
                    $paths[] = $migrationPath;
                }
            }
        }

        // ── Local modules — always tenant ──────────────────
        if ($scope === 'tenant') {
            $theme     = Cms::getCurrentTheme();
            $localPath = base_path(
                'cms' . DIRECTORY_SEPARATOR .
                    'local' . DIRECTORY_SEPARATOR .
                    'themes' . DIRECTORY_SEPARATOR .
                    $theme
            );

            foreach (glob($localPath . '/*/module.json') as $jsonFile) {
                $migrationPath = dirname($jsonFile)
                    . DIRECTORY_SEPARATOR
                    . 'Database'
                    . DIRECTORY_SEPARATOR
                    . 'Migration';

                if (\File::exists($migrationPath)) {
                    $paths[] = $migrationPath;
                }
            }
        }

        return $paths;
    }

    /**
     * Copy migrations to tmp and run
     */
    protected function copyAndMigrate(array $migrationPaths, string $type): void
    {
        $tmpPath = base_path()
            . DIRECTORY_SEPARATOR . 'cms'
            . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'migration'
            . DIRECTORY_SEPARATOR . $type;

        // Create tmp folder
        if (!\File::exists($tmpPath)) {
            \File::makeDirectory($tmpPath, 0777, true);
        }

        // Clean tmp folder
        \File::cleanDirectory($tmpPath);

        // Copy all migration files
        foreach ($migrationPaths as $migrationPath) {
            foreach (\File::files($migrationPath) as $file) {
                \File::copy(
                    $file,
                    $tmpPath . DIRECTORY_SEPARATOR . basename($file)
                );
            }
        }

        // Run migrations
        if (count(\File::files($tmpPath)) > 0) {
            $this->call('migrate', [
                '--path' => 'cms'
                    . DIRECTORY_SEPARATOR . 'tmp'
                    . DIRECTORY_SEPARATOR . 'migration'
                    . DIRECTORY_SEPARATOR . $type
            ]);
        } else {
            $this->warn("No migrations found for [{$type}]");
        }

        // Cleanup tmp
        \File::cleanDirectory($tmpPath);
    }
}
