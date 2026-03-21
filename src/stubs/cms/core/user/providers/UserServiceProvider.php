<?php
namespace cms\core\user\providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Ramesh\Cms\Traits\TenancyRoutes;
use Cms;

class UserServiceProvider extends ServiceProvider
{
    use TenancyRoutes;
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        /*
        $configPath = __DIR__ . '/../config/config.php';

        $this->mergeConfigFrom($configPath, 'modules');
        $this->publishes([
            $configPath => config_path('modules.php'),
        ], 'config');
        */
        $this->registerEvents();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerViews();
        $this->registerRoute();
        $this->registerAdminRoute();
        $this->registerMiddleware();

    }

    public function registerRoute()
    {
       
        Route::prefix('')
            ->middleware($this->webMiddleware()) 
            ->namespace('cms\core\user\Controllers')
            ->group(__DIR__ . '/../routes.php');


    }
    public function registerAdminRoute()
    {
       

        Route::prefix('administrator')
             ->middleware($this->adminMiddleware())
            ->namespace('cms\core\user\Controllers')
            ->group(__DIR__ . '/../adminroutes.php');


    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $theme = Cms::getCurrentTheme();

        $viewPath = resource_path('views/modules/user');

        //$sourcePath = __DIR__.'/../resources/views';
        $Path = __DIR__.'/../resources/views';
        $sourcePath = base_path().'/cms/local/'.$theme.'/user/resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ]);
        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/user';
        }, [$Path]), [$sourcePath,$Path]), 'user');
    }
    /*
     * register middleware
     */
    public function registerMiddleware()
    {
        app('router')->aliasMiddleware('UserAuth', \cms\core\user\Middleware\UserCheck::class);
    }
    /*
     * register events
     */
    public function registerEvents()
    {
        $this->app->register(UserEventServiceProvider::class);
    }


}
