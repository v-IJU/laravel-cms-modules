<?php

namespace cms\Test\Providers;

use Illuminate\Support\ServiceProvider;
use Route;
use Cms;
use Ramesh\Cms\Traits\TenancyRoutes;
class TestServiceProvider extends ServiceProvider
{
    use TenancyRoutes;
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {

       // $this->registerViews();
        //$this->registerRoute();
        //$this->registerAdminRoute();
        //$this->registerMiddleware();
        //$this->registerApiRoutes();
    }

    public function registerRoute()
    {
        Route::prefix('')
            ->middleware($this->webMiddleware()) 
            ->namespace('cms\Test\Controllers')
            ->group(__DIR__ . '/../routes.php');

    }

    public function registerAdminRoute()
    {

        Route::prefix('administrator')
            ->middleware($this->adminMiddleware())
            ->namespace('cms\Test\Controllers')
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

        $viewPath = resource_path('views/modules/Test');

        //$sourcePath = __DIR__.'/../resources/views';
        $Path = __DIR__.'/../resources/views';
        $sourcePath = base_path().'/cms/local/'.$theme.'/Test/resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ]);
        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/Test';
        }, [$Path]), [$sourcePath,$Path]), 'Test');
    }
    /*
     * register middleware
     */
    public function registerMiddleware()
    {
        app('router')->aliasMiddleware('MiddleWareName', middlewarepath::class);
    }

    /*
     * register api routes
     */
    public function registerApiRoutes() {

        Route::prefix('api')
            ->middleware(['UserAuthForApi'])
            ->namespace('cms\Test\Controllers')
            ->group(__DIR__ . '/../apiroutes.php');
    }

}
