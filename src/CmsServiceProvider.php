<?php

namespace Ramesh\Cms;

use Illuminate\Support\ServiceProvider;
use Ramesh\Cms\Providers\ModuleServiceProvider;
use Ramesh\Cms\Providers\CommandProvider;
use Ramesh\Cms\Controller\CmsController;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Step 1: Register Cms singleton FIRST ──────────────
        $this->app->singleton('Cms', function ($app) {
            return new CmsController();
        });

        // ── Step 2: Register Cms alias immediately ─────────────
        if (!class_exists('Cms')) {
            class_alias(\Ramesh\Cms\Facades\Cms::class, 'Cms');
        }

        // ── Step 3: Register core modules namespace ────────────
        // This is what loads cms/core/* modules
        $loader = require base_path('vendor/autoload.php');
        $loader->setPsr4('cms\\core\\', base_path('cms/core'));

        // ── Step 4: Merge configs ──────────────────────────────
        if (file_exists(__DIR__ . '/config/lfm.php')) {
            $this->mergeConfigFrom(__DIR__ . '/config/lfm.php', 'lfm');
        }
        if (file_exists(__DIR__ . '/config/cms.php')) {
            $this->mergeConfigFrom(__DIR__ . '/config/cms.php', 'cms');
        }

        // ── Step 5: Register sub providers ────────────────────
        $this->app->register(ModuleServiceProvider::class);
        $this->app->register(CommandProvider::class);
    }

    public function boot(): void
    {
         // ── Register CmsGate middleware ────────────────────────
        app('router')->aliasMiddleware(
            'cgate',
            \Ramesh\Cms\Middleware\CmsGate::class
        );
        // ── Publish configs ────────────────────────────────────
        $this->publishes([
            __DIR__ . '/config/lfm.php' => config_path('lfm.php'),
            __DIR__ . '/config/cms.php' => config_path('cms.php'),
        ], 'cms-config');

        // ── Publish skin assets ────────────────────────────────
        $this->publishes([
            __DIR__ . '/stubs/skin' => public_path('skin'),
        ], 'cms-assets');

        // ── Publish cms folder structure ───────────────────────
        $this->publishes([
            __DIR__ . '/stubs/cms' => base_path('cms'),
        ], 'cms-structure');

        // ── Load views ─────────────────────────────────────────
        if (is_dir(__DIR__ . '/views')) {
            $this->loadViewsFrom(__DIR__ . '/views', 'cms');
        }

        // ── Load migrations ────────────────────────────────────
        if (is_dir(__DIR__ . '/database/migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        }

        // ── Load routes ────────────────────────────────────────
        if (file_exists(__DIR__ . '/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        }
    }
}