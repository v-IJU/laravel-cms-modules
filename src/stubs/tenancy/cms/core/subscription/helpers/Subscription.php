<?php

namespace cms\core\subscription\helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Subscription
{
    /**
     * Get current tenant plan from central DB
     */
    public static function getPlan(): ?object
    {
        if (!config('cms.tenancy_enabled')) return null;

        $tenantId = tenant('id');
        if (!$tenantId) return null;

        return Cache::remember("tenant_{$tenantId}_plan", 3600, function () use ($tenantId) {
            return DB::connection('central')
                ->table('tenant_plans')
                ->join('plans', 'plans.id', '=', 'tenant_plans.plan_id')
                ->where('tenant_plans.tenant_id', $tenantId)
                ->where('tenant_plans.status', 'active')
                ->select(
                    'plans.*',
                    'tenant_plans.ends_at as subscription_ends_at',
                    'tenant_plans.status as subscription_status',
                    'tenant_plans.trial_ends_at'
                )
                ->first();
        });
    }

    /**
     * Check if tenant subscription is active
     */
    public static function isActive(): bool
    {
        if (!config('cms.tenancy_enabled')) return true;

        $plan = self::getPlan();
        if (!$plan) return false;

        if ($plan->subscription_ends_at && now()->gt($plan->subscription_ends_at)) {
            return false;
        }

        return $plan->subscription_status === 'active';
    }

    /**
     * Check if tenant can access a module
     */
    public static function canAccessModule(string $module): bool
    {
        if (!config('cms.tenancy_enabled')) return true;

        $plan = self::getPlan();
        if (!$plan) return false;

        // Check wildcard access
        $allAccess = DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $plan->id)
            ->where('feature_key', 'module_all')
            ->where('feature_value', 'true')
            ->exists();

        if ($allAccess) return true;

        // Check specific module access
        return DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $plan->id)
            ->where('feature_key', "module_{$module}")
            ->where('feature_value', 'true')
            ->exists();
    }

    /**
     * Check if tenant can add more users
     */
    public static function canAddUser(): bool
    {
        if (!config('cms.tenancy_enabled')) return true;

        $plan = self::getPlan();
        if (!$plan) return false;
        if ($plan->max_users === -1) return true;

        $currentUsers = DB::table('users')->count();
        return $currentUsers < $plan->max_users;
    }

    /**
     * Get plan name
     */
    public static function getPlanName(): string
    {
        $plan = self::getPlan();
        return $plan ? $plan->name : 'No Plan';
    }

    /**
     * Get days remaining
     */
    public static function getDaysRemaining(): ?int
    {
        $plan = self::getPlan();
        if (!$plan || !$plan->subscription_ends_at) return null;
        return now()->diffInDays($plan->subscription_ends_at, false);
    }

    /**
     * Clear plan cache
     */
    public static function clearCache(): void
    {
        $tenantId = tenant('id');
        if ($tenantId) {
            Cache::forget("tenant_{$tenantId}_plan");
        }
    }
}
