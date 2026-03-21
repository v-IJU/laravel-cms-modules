<?php

namespace Ramesh\Cms\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'cms:install';
    protected $description = 'Install Laravel CMS — one command setup';

    public function handle(): void
    {
        $this->showWelcome();

        // ── Step 1: Ask tenancy ────────────────────────────
        $wantsTenancy = $this->confirm(
            'Do you want multi-tenancy support (SaaS)?',
            false
        );

        if ($wantsTenancy && !$this->checkTenancyInstalled()) {
            return;
        }

        // ── Step 2: Publish config ─────────────────────────
        $this->info('📦 [1/7] Publishing config files...');
        $this->call('vendor:publish', [
            '--tag'   => 'cms-config',
            '--force' => true,
        ]);

        // ── Step 3: Publish cms base structure ─────────────
        $this->info('📦 [2/7] Publishing CMS modules...');
        $this->call('vendor:publish', [
            '--tag'   => 'cms-structure',
            '--force' => false,
        ]);

        // ── Step 4: Publish assets ─────────────────────────
        $this->info('📦 [3/7] Publishing skin assets...');
        $this->call('vendor:publish', [
            '--tag'   => 'cms-assets',
            '--force' => false,
        ]);

        // ── Step 5: Tenancy or normal setup ───────────────
        if ($wantsTenancy) {
            $this->info('🏢 [4/7] Setting up tenancy...');
            $this->setupTenancy();
        } else {
            $this->info('⚙️  [4/7] Setting up single app mode...');
            $this->setupNormal();
        }

        // ── Step 6: Run migrations ─────────────────────────
        $this->info('🗄️  [5/7] Running migrations...');

        $this->ensureCentralDatabaseExists();

        // Laravel standard tables (cache, jobs, sessions)
        $this->call('migrate');

        // Core module migrations → central DB
        if ($wantsTenancy) {
            // Tenancy mode — central DB only
            $this->call('cms-migrate', ['--db' => 'central']);
        } else {
            // Normal mode — all modules single DB
            $this->call('cms-migrate', ['--db' => 'all']);
        }

        // ── Step 6: Register modules and menus ────────────
        $this->info('📋 [6/7] Registering modules and menus...');
        $this->call('update:cms-module');
        $this->call('update:cms-menu');

        if ($this->commandExists('update:cms-plugins')) {
            $this->call('update:cms-plugins');
        }

        // ── Step 7: Seed data ──────────────────────────────
        $this->info('🌱 [7/7] Seeding data...');
        $this->call('db:cms-seed');

         $this->info(' Seeding Done');

        // ── Save install state ─────────────────────────────
        $this->updateCmsConfig('installed', true);
        $this->updateCmsConfig('tenancy_enabled', $wantsTenancy);
        $this->updateCmsConfig(
            'install_mode',
            $wantsTenancy ? 'tenancy' : 'normal'
        );

        // ── Done ───────────────────────────────────────────
        $this->showSuccess($wantsTenancy);
    }

    // ──────────────────────────────────────────────────────
    // Setup methods
    // ──────────────────────────────────────────────────────

    protected function setupNormal(): void
    {
        // Normal mode — just cms modules, no tenancy
        $this->updateCmsConfig('tenancy_enabled', false);
        $this->updateCmsConfig('install_mode', 'normal');
        $this->info('✅ Single app mode configured!');
    }

    protected function setupTenancy(): void
    {
        // ── 1. Publish Tenant model ────────────────────────
        // Our custom Tenant model with plan_id, name, email
        $this->call('vendor:publish', [
            '--tag'   => 'cms-tenant-model',
            '--force' => true,
        ]);

        // ── 2. Publish tenancy stubs ───────────────────────
        // Publishes:
        //   → subscription core module into cms/core/subscription/
        //   → tenant migrations into database/migrations/tenant/
        $this->call('vendor:publish', [
            '--tag'   => 'cms-tenancy-stubs',
            '--force' => false,
        ]);

        // ── 3. Register TenancyServiceProvider ────────────
        // File already created by tenancy:install
        // Just register it in bootstrap/providers.php
        $this->registerProvider('App\\Providers\\TenancyServiceProvider');

        // ── 4. Configure tenancy.php ───────────────────────
        // Point to our Tenant model + TenantDatabaseSeeder
        $this->configureTenancyConfig();

        $this->updateCmsConfig('tenancy_enabled', true);
        $this->updateCmsConfig('install_mode', 'tenancy');

        $this->info('✅ Tenancy + Subscription module configured!');
    }

    // ──────────────────────────────────────────────────────
    // Tenancy helpers
    // ──────────────────────────────────────────────────────

    protected function checkTenancyInstalled(): bool
    {
        // Check package installed
        if (!class_exists(\Stancl\Tenancy\TenancyServiceProvider::class)) {
            $this->error('stancl/tenancy is not installed!');
            $this->line('');
            $this->line('Please run:');
            $this->line('  composer require stancl/tenancy');
            $this->line('  php artisan tenancy:install');
            $this->line('  php artisan cms:install');
            return false;
        }

        // Check tenancy:install was run
        if (!file_exists(config_path('tenancy.php'))) {
            $this->error('tenancy:install not run yet!');
            $this->line('');
            $this->line('Please run:');
            $this->line('  php artisan tenancy:install');
            $this->line('  php artisan cms:install');
            return false;
        }

        $this->info('✅ stancl/tenancy found!');
        return true;
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

    // ──────────────────────────────────────────────────────
    // Config helper
    // ──────────────────────────────────────────────────────

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

    // ──────────────────────────────────────────────────────
    // Utility
    // ──────────────────────────────────────────────────────

    protected function commandExists(string $command): bool
    {
        return $this->getApplication()->has($command);
    }

    // ──────────────────────────────────────────────────────
    // UI
    // ──────────────────────────────────────────────────────

    protected function showWelcome(): void
    {
        $this->line('');
        $this->line('  ╔══════════════════════════════════════════╗');
        $this->line('  ║       Laravel CMS — Installation          ║');
        $this->line('  ║       viju/laravel-cms-modules            ║');
        $this->line('  ╚══════════════════════════════════════════╝');
        $this->line('');
    }

    protected function showSuccess(bool $tenancy): void
    {
        $this->line('');
        $this->line('  ╔══════════════════════════════════════════╗');
        $this->line('  ║     ✅ Laravel CMS Installed!             ║');
        $this->line('  ╚══════════════════════════════════════════╝');
        $this->line('');
        $this->info('  Visit    : /administrator');
        $this->info('  Username : admin');
        $this->info('  Password : admin123');
        $this->line('');

        if ($tenancy) {
            $this->info('  Mode     : Multi-tenant SaaS ✅');
            $this->line('');
            $this->info('  Next steps:');
            $this->info('  → Create tenant : php artisan cms:create-tenant');
            $this->info('  → List tenants  : php artisan cms:list-tenants');
        } else {
            $this->info('  Mode     : Single app');
            $this->line('');
            $this->info('  Upgrade later:');
            $this->info('  → php artisan cms:setup-tenancy');
        }

        $this->line('');
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
}
