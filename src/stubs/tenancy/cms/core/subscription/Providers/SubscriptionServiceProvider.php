<?php

namespace cms\core\subscription\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Ramesh\Cms\Traits\TenancyRoutes;
class SubscriptionServiceProvider extends ServiceProvider
{
    use TenancyRoutes;
    public function register(): void {}

    public function boot(): void
    {
        $this->registerMiddleware();
        $this->registerViews();
        $this->registerAdminRoutes();
        $this->registerBladeDirectives();
    }

    protected function registerMiddleware(): void
    {
        app('router')->aliasMiddleware(
            'subscription.gate',
            \cms\core\subscription\Middleware\SubscriptionGate::class
        );
    }

    protected function registerViews(): void
    {
        $this->app['view']->addNamespace(
            'subscription',
            base_path('cms/core/subscription/resources/views')
        );
    }

    protected function registerAdminRoutes(): void
    {
        $routeFile = base_path('cms/core/subscription/adminroutes.php');

        if (file_exists($routeFile)) {
            $this->app['router']
                ->middleware($this->adminMiddleware()) 
                ->group($routeFile);
        }
    }

    protected function registerBladeDirectives(): void
    {
        // ── Module access ──────────────────────────────────
        // @canModule('blog') ... @endcanModule
        Blade::directive('canModule', function ($module) {
            return "<?php if(\\cms\\core\\subscription\\helpers\\Subscription::canAccessModule({$module})): ?>";
        });

        Blade::directive('endcanModule', function () {
            return "<?php endif; ?>";
        });

        // ── Feature flags ──────────────────────────────────
        // @hasFeature('can_export_pdf') ... @endhasFeature
        Blade::directive('hasFeature', function ($feature) {
            return "<?php if(\\cms\\core\\subscription\\helpers\\Subscription::hasFeature({$feature})): ?>";
        });

        Blade::directive('endhasFeature', function () {
            return "<?php endif; ?>";
        });

        // ── Can create ─────────────────────────────────────
        // @canCreate('posts', $count) ... @endcanCreate
        Blade::directive('canCreate', function ($expression) {
            [$key, $count] = explode(',', $expression);
            return "<?php if(\\cms\\core\\subscription\\helpers\\Subscription::canCreate(trim({$key}), {$count})): ?>";
        });

        Blade::directive('endcanCreate', function () {
            return "<?php endif; ?>";
        });

        // ── Subscription active ────────────────────────────
        // @subscriptionActive ... @endsubscriptionActive
        Blade::directive('subscriptionActive', function () {
            return "<?php if(\\cms\\core\\subscription\\helpers\\Subscription::isActive()): ?>";
        });

        Blade::directive('endsubscriptionActive', function () {
            return "<?php endif; ?>";
        });
    }
}
