<?php

namespace cms\core\subscription\Middleware;

use Closure;
use Illuminate\Http\Request;
use cms\core\subscription\helpers\Subscription;

class SubscriptionGate
{
    /**
     * Check if tenant can access this module
     * Usage in routes:
     *   Route::middleware(['web', 'Admin', 'subscription.gate:blog'])
     */
    public function handle(Request $request, Closure $next, string $module = null): mixed
    {
        // Skip if tenancy not enabled
        if (!config('cms.tenancy_enabled')) {
            return $next($request);
        }

        // Skip for central domain
        if (!tenancy()->initialized) {
            return $next($request);
        }

        // Check subscription active
        if (!Subscription::isActive()) {
            return redirect()
                ->route('backenddashboard')
                ->with('error', 'Your subscription has expired. Please contact support.');
        }

        // Check module access
        if ($module && !Subscription::canAccessModule($module)) {
            return redirect()
                ->route('subscription.upgrade')
                ->with('error', "Your plan does not include [{$module}] module. Please upgrade.");
        }

        return $next($request);
    }
}
