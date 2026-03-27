<?php

namespace Ramesh\Cms\Commands;

use Illuminate\Console\Command;

class SetupTenancyCommand extends Command
{
    protected $signature   = 'cms:setup-tenancy';
    protected $description = 'Upgrade existing CMS to multi-tenancy';

    public function handle(): void
    {
        $this->info('Upgrading to multi-tenancy...');

        // ── Already enabled? ───────────────────────────────
        if (config('cms.tenancy_enabled')) {
            $this->warn('Tenancy is already enabled!');
            return;
        }

        // ── Check stancl/tenancy installed ─────────────────
        if (!class_exists(\Stancl\Tenancy\TenancyServiceProvider::class)) {
            $this->error('stancl/tenancy not installed!');
            $this->line('Run:');
            $this->line('  composer require stancl/tenancy');
            $this->line('  php artisan tenancy:install');
            $this->line('  php artisan cms:setup-tenancy');
            return;
        }

        // ── Check tenancy:install was run ──────────────────
        if (!file_exists(config_path('tenancy.php'))) {
            $this->error('Please run: php artisan tenancy:install first!');
            return;
        }

        if (!$this->confirm('Upgrade this app to multi-tenancy?')) {
            return;
        }

        // ── Step 1: Publish Tenant model ───────────────────
        $this->info('📦 [1/8] Publishing Tenant model...');
        $this->call('vendor:publish', [
            '--tag'   => 'cms-tenant-model',
            '--force' => true,
        ]);

        // ── Step 2: Publish our TenancyServiceProvider ────
        $this->info('📦 [2/8] Publishing TenancyServiceProvider...');
        $this->call('vendor:publish', [
            '--tag'   => 'cms-tenancy-provider',
            '--force' => true,
        ]);

        $this->info('📦 [x/8] Publishing tenancy middleware...');
        $this->call('vendor:publish', [
            '--tag'   => 'cms-tenancy-middleware',
            '--force' => true,
        ]);

        // ── Step 3: Publish subscription module ───────────
        $this->info('📦 [3/8] Publishing subscription module...');
        $this->call('vendor:publish', [
            '--tag'   => 'cms-tenancy-stubs',
            '--force' => false,
        ]);

        // ── Step 4: Register TenancyServiceProvider ───────
        $this->info('📝 [4/8] Registering TenancyServiceProvider...');
        $this->registerProvider('App\\Providers\\TenancyServiceProvider');

        // ── Step 5: Configure tenancy.php ─────────────────
        $this->info('⚙️  [5/8] Configuring tenancy.php...');
        $this->configureTenancyConfig();

        // ── Step 6: Add central DB connection ─────────────
        // Must happen BEFORE migrations
        // central connection never gets switched by tenancy
        $this->info('🔌 [6/8] Adding central DB connection...');
        $this->addCentralConnection();

        // ── Step 7: Run central migrations ────────────────
        $this->info('🗄️  [7/8] Running central migrations...');
        $this->ensureCentralDatabaseExists();
        $this->call('config:clear'); // ← clear after adding central connection
        $this->call('migrate');
        $this->call('cms-migrate', [
            '--db'             => 'central',
            '--no-interaction' => true,
        ]);

        // ── Step 8: Register modules + seed plans ─────────
        $this->info('📋 [8/8] Registering modules and seeding plans...');

        // if ($this->getApplication()->has('update:cms-module')) {
        //     $this->call('update:cms-module');
        //     $this->info('Module Update done');
        // } else {
        //     $this->warn('update:cms-module not found — run manually after setup');
        // }
       // \Artisan::call('update:cms-module');

        // if ($this->getApplication()->has('update:cms-menu')) {
        //     $this->call('update:cms-menu');
        //     $this->info('Menu Update done');
        // } else {
        //     $this->warn('update:cms-menu not found — run manually after setup');
        // }

        exec('php artisan update:cms-module');
        exec('php artisan update:cms-menu');

        $this->info('Menu and Module  Update done');

        // Seed admin
        $adminSeeder = 'cms\core\user\Database\Seeds\AdminSeeder';
        if (class_exists($adminSeeder)) {
            $this->call('db:seed', ['--class' => $adminSeeder]);
            $this->info('Admin Seeder Done and group');
        }

        // Seed plans (reads from modules table)
        $planSeeder = 'cms\core\subscription\Database\Seeds\PlanSeeder';
        if (class_exists($planSeeder)) {
            $this->call('db:seed', ['--class' => $planSeeder]);
            $this->info('Plan Seeder Done and Features');
        } else {
            $this->warn('PlanSeeder not found — seed plans manually');
        }

        // ── Update cms config ──────────────────────────────
        $this->updateCmsConfig('tenancy_enabled', true);
        $this->updateCmsConfig('install_mode', 'tenancy');

        // ── Clear all caches ───────────────────────────────
        $this->call('config:clear');
        $this->call('cache:clear');

        // ── Done ───────────────────────────────────────────
        $this->line('');
        $this->line('  ╔══════════════════════════════════════════╗');
        $this->line('  ║     ✅ Tenancy Setup Complete!            ║');
        $this->line('  ╚══════════════════════════════════════════╝');
        $this->line('');
        $this->info('  Next steps:');
        $this->info('  → Create tenant : php artisan cms:create-tenant');
        $this->info('  → List tenants  : php artisan cms:list-tenants');
        $this->line('');
    }

    protected function registerProvider(string $provider): void
    {
        $file = base_path('bootstrap/providers.php');

        if (!file_exists($file)) {
            $this->warn('bootstrap/providers.php not found — skipping');
            return;
        }

        $content = file_get_contents($file);

        if (!str_contains($content, $provider)) {
            $content = str_replace(
                '];',
                "    {$provider}::class,\n];",
                $content
            );
            file_put_contents($file, $content);
            $this->info("✅ {$provider} registered!");
        } else {
            $this->info("ℹ️  {$provider} already registered");
        }
    }

    protected function configureTenancyConfig(): void
    {
        $file = config_path('tenancy.php');
        if (!file_exists($file)) {
            $this->warn('tenancy.php not found — skipping');
            return;
        }

        $content = file_get_contents($file);

        // Use our custom Tenant model
        $content = str_replace(
            "use Stancl\\Tenancy\\Database\\Models\\Tenant;",
            "use App\\Models\\Tenant;",
            $content
        );

        // Use TenantDatabaseSeeder
        $content = str_replace(
            "'--class' => 'DatabaseSeeder'",
            "'--class' => 'TenantDatabaseSeeder'",
            $content
        );

        // Add APP_DOMAIN to central domains
        $appDomain = env('APP_DOMAIN', 'localhost');
        $content = str_replace(
            "'central_domains' => [\n        '127.0.0.1',\n        'localhost',\n    ],",
            "'central_domains' => [\n        '{$appDomain}',\n        '127.0.0.1',\n        'localhost',\n    ],",
            $content
        );

        file_put_contents($file, $content);
        $this->info('✅ tenancy.php configured!');
    }

    protected function updateCmsConfig(string $key, mixed $value): void
    {
        $file = config_path('cms.php');
        if (!file_exists($file)) return;

        $content  = file_get_contents($file);
        $valueStr = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value)  => (string) $value,
            default         => "'{$value}'",
        };

        $content = preg_replace(
            "/(['\"]" . preg_quote($key, '/') . "['\"])\s*=>\s*[^,\n]+/",
            "$1 => {$valueStr}",
            $content
        );

        file_put_contents($file, $content);
    }

    protected function ensureCentralDatabaseExists(): void
    {
        $connection = config('database.default');
        $dbName     = config("database.connections.{$connection}.database");
        $dbHost     = config("database.connections.{$connection}.host");
        $dbPort     = config("database.connections.{$connection}.port", 3306);
        $dbUser     = config("database.connections.{$connection}.username");
        $dbPassword = config("database.connections.{$connection}.password");

        // ── Use root for DB creation ───────────────────────
        // App user may not have CREATE DATABASE privilege
        $rootUser     = env('DB_ROOT_USERNAME', 'root');
        $rootPassword = env('DB_ROOT_PASSWORD', config("database.connections.{$connection}.password"));

        try {
            // Connect as root
            $pdo = new \PDO(
                "mysql:host={$dbHost};port={$dbPort}",
                $rootUser,
                $rootPassword,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // Check if DB exists
            $exists = $pdo->query(
                "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA
             WHERE SCHEMA_NAME = '{$dbName}'"
            )->fetch();

            if (!$exists) {
                $this->warn("Database [{$dbName}] not found — creating...");

                // Create DB
                $pdo->exec(
                    "CREATE DATABASE `{$dbName}`
                 CHARACTER SET utf8mb4
                 COLLATE utf8mb4_unicode_ci"
                );

                // Grant privileges to app user
                $pdo->exec(
                    "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'%'"
                );
                $pdo->exec("FLUSH PRIVILEGES");

                $this->info("✅ Database [{$dbName}] created!");
                $this->info("✅ Privileges granted to [{$dbUser}]!");
            } else {
                $this->info("✅ Database [{$dbName}] exists!");
            }
        } catch (\PDOException $e) {
            $this->error('Cannot connect to MySQL!');
            $this->line('');
            $this->line('Make sure these are set in .env:');
            $this->line("  DB_ROOT_USERNAME=root");
            $this->line("  DB_ROOT_PASSWORD=your_root_password");
            $this->line('');
            $this->line('Or create DB manually:');
            $this->line("  CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $this->line("  GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'%';");
            throw $e;
        }
    }

    protected function addCentralConnection(): void
    {
        $file = config_path('database.php');
        if (!file_exists($file)) return;

        $content = file_get_contents($file);

        if (str_contains($content, "'central'")) {
            $this->info('ℹ️  Central connection already exists');
            return;
        }

        $centralConfig = <<<'PHP'

        // ── Central DB — always points to central, never switched by tenancy
        'central' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'forge'),
            'username'  => env('DB_USERNAME', 'forge'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
        ],

    PHP;

        $content = str_replace(
            "'connections' => [",
            "'connections' => [" . $centralConfig,
            $content
        );

        file_put_contents($file, $content);
        $this->info('✅ Central connection added!');
    }
}
