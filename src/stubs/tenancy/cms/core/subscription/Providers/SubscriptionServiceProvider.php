<?php

namespace cms\core\subscription\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerViews();
        $this->registerAdminRoot();
        $this->registerBladeDirectives();
    }

    protected function registerViews(): void
    {
        $this->app['view']->addNamespace(
            'subscription',
            base_path('cms/core/subscription/resources/views')
        );
    }

    protected function registerAdminRoot(): void
    {
        $routeFile = base_path('cms/core/subscription/adminroutes.php');

        if (file_exists($routeFile)) {
            $this->app['router']
                ->middleware(['web', 'auth'])
                ->group($routeFile);
        }
    }

    protected function registerBladeDirectives(): void
    {
        // @canModule('blog') ... @endcanModule
        Blade::directive('canModule', function ($module) {
            return "<?php if(\\cms\\core\\subscription\\helpers\\Subscription::canAccessModule({$module})): ?>";
        });

        Blade::directive('endcanModule', function () {
            return "<?php endif; ?>";
        });

        // @subscriptionActive ... @endsubscriptionActive
        Blade::directive('subscriptionActive', function () {
            return "<?php if(\\cms\\core\\subscription\\helpers\\Subscription::isActive()): ?>";
        });

        Blade::directive('endsubscriptionActive', function () {
            return "<?php endif; ?>";
        });
    }
}
