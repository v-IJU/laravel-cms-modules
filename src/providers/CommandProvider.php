<?php

namespace Ramesh\Cms\Providers;

use Illuminate\Support\ServiceProvider;

class CommandProvider extends ServiceProvider
{
    protected array $commands = [
        // ── Installation ────────────────────────────────
        \Ramesh\Cms\Commands\InstallCommand::class,
        \Ramesh\Cms\Commands\SetupTenancyCommand::class,
        \Ramesh\Cms\Commands\CreateTenantCommand::class,

        // ── Module scaffolding ───────────────────────────
        \Ramesh\Cms\Commands\ModuleCommand::class,
        \Ramesh\Cms\Commands\MakeController::class,
        \Ramesh\Cms\Commands\MakeModel::class,
        \Ramesh\Cms\Commands\MakeMigration::class,
        \Ramesh\Cms\Commands\MakeCommand::class,
        \Ramesh\Cms\Commands\MakeEvent::class,
        \Ramesh\Cms\Commands\MakeJob::class,
        \Ramesh\Cms\Commands\MakeListener::class,
        \Ramesh\Cms\Commands\MakeMail::class,
        \Ramesh\Cms\Commands\MakeMiddleware::class,
        \Ramesh\Cms\Commands\MakeNotification::class,
        \Ramesh\Cms\Commands\MakeProvider::class,
        \Ramesh\Cms\Commands\MakeSeeder::class,
        \Ramesh\Cms\Commands\MakeCrudRoutes::class,
        \Ramesh\Cms\Commands\MakeCrudViews::class,

        // ── Database ─────────────────────────────────────
        \Ramesh\Cms\Commands\Migrate::class,
        \Ramesh\Cms\Commands\Seed::class,
    ];

    public function register(): void
    {
        $this->registerCommands();
    }

    public function boot(): void {}

    protected function registerCommands(): void
    {
        $existing = array_filter(
            $this->commands,
            fn(string $command) => class_exists($command)
        );

        $this->commands($existing);
    }
}
