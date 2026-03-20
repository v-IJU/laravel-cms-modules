<?php

namespace Ramesh\Cms\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerLibrary();

        // Guard — only run if cms config exists and is loaded
        if ($this->isCmsConfigLoaded()) {
            $this->registerNamespace();
            $this->registerComposerAutoload();
            $this->registerHelpers();
        }
    }

    public function boot(): void
    {
        //
    }

    /**
     * Check if cms config is properly loaded
     */
    protected function isCmsConfigLoaded(): bool
    {
        try {
            $config = config('cms');
            return !empty($config);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Register module providers
     */
    /**
 * Register module providers
 */
    protected function registerProviders(): void
    {
        try {
            $providers = app('Cms')->allModuleProvider();

            foreach ($providers as $provider) {
                if (class_exists($provider)) {
                    $this->app->register($provider);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('CMS registerProviders error: ' . $e->getMessage());
        }
    }

    /**
     * Register composer autoload for modules
     */
    protected function registerComposerAutoload(): void
    {
        try {
            $loader    = require base_path('vendor/autoload.php');
            $composers = app('Cms')->allModulesComposer();

            foreach ($composers as $composer) {
                if (!isset($composer['autoload'])) continue;

                foreach ($composer['autoload'] as $autoload) {
                    foreach ($autoload as $key => $value) {
                        $loader->setPsr4(
                            $key,
                            base_path() . DIRECTORY_SEPARATOR . $value
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail during initial setup
        }
    }

    /**
     * Register module namespaces
     */
   /**
 * Register module namespaces
 */
   
protected function registerNamespace(): void
{
    try {
        $modules = app('Cms')->allModules();
        $loader  = require base_path('vendor/autoload.php');

        foreach ($modules as $module) {
            if (!isset($module['type'], $module['name'], $module['path'])) {
                continue;
            }

            if ($module['type'] === 'local') {
                // local: cms\ModuleName\...
                $loader->setPsr4(
                    'cms\\' . $module['name'] . '\\',
                    $module['path']
                );
            }

            // core already registered in CmsServiceProvider
            // cms\core\ → base_path('cms/core')
        }

        $this->registerProviders();
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('CMS registerNamespace: ' . $e->getMessage());
    }
}
    /**
     * Register module helper aliases
     */
    protected function registerHelpers(): void
    {
        try {
            $helpers = app('Cms')->allModulesHelpers();

            foreach ($helpers as $alias => $class) {
                $this->app->booting(function () use ($alias, $class) {
                    AliasLoader::getInstance()->alias($alias, $class);
                });
            }
        } catch (\Exception $e) {
            // Silently fail during initial setup
        }
    }

    /**
     * Register third party libraries
     */
    protected function registerLibrary(): void
    {
        // Laravel File Manager — handle namespace change between versions
        $lfmProviders = [
            \UniSharp\LaravelFilemanager\LaravelFilemanagerServiceProvider::class,  // v2.6+
            \Unisharp\Laravelfilemanager\LaravelFilemanagerServiceProvider::class,  // v2.2
        ];

        foreach ($lfmProviders as $provider) {
            if (class_exists($provider)) {
                $this->app->register($provider);
                break;
            }
        }

        // Intervention Image — handle v2 vs v3
        $imageProviders = [
            \Intervention\Image\Laravel\ServiceProvider::class,   // v3
            \Intervention\Image\ImageServiceProvider::class,      // v2
        ];

        foreach ($imageProviders as $provider) {
            if (class_exists($provider)) {
                $this->app->register($provider);
                AliasLoader::getInstance()->alias(
                    'Image',
                    \Intervention\Image\Facades\Image::class
                );
                break;
            }
        }
    }
}