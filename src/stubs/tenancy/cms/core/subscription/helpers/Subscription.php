<?php

namespace cms\core\subscription\helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Subscription
{
    // ──────────────────────────────────────────────────────
    // Plan helpers
    // ──────────────────────────────────────────────────────

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
                ->whereIn('tenant_plans.status', ['active', 'trial'])
                ->select(
                    'plans.*',
                    'tenant_plans.ends_at as subscription_ends_at',
                    'tenant_plans.status as subscription_status',
                    'tenant_plans.trial_ends_at'
                )
                ->first();
        });
    }

    public static function isActive(): bool
    {
        if (!config('cms.tenancy_enabled')) return true;

        $plan = self::getPlan();
        if (!$plan) return false;

        if ($plan->subscription_ends_at && now()->gt($plan->subscription_ends_at)) {
            return false;
        }

        return in_array($plan->subscription_status, ['active', 'trial']);
    }

    // ──────────────────────────────────────────────────────
    // Module access
    // ──────────────────────────────────────────────────────

    /**
     * Check if tenant can access a module
     * Priority: tenant override > plan features
     */
    public static function canAccessModule(string $module): bool
    {
        if (!config('cms.tenancy_enabled')) return true;

        $tenantId = tenant('id');
        if (!$tenantId) return false;

        // ── Check tenant override first ────────────────────
        $override = self::getTenantOverride($tenantId, $module);

        if ($override !== null) {
            return $override->is_enabled;
        }

        // ── Check plan features ────────────────────────────
        return self::planAllowsModule($module);
    }

    /**
     * Check plan allows module (no override)
     */
    public static function planAllowsModule(string $module): bool
    {
        $plan = self::getPlan();
        if (!$plan) return false;

        // Check wildcard
        $hasAll = DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $plan->id)
            ->where('feature_key', 'module_all')
            ->where('feature_value', 'true')
            ->exists();

        if ($hasAll) return true;

        return DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $plan->id)
            ->where('feature_key', 'module_' . $module)
            ->where('feature_value', 'true')
            ->exists();
    }

    // ──────────────────────────────────────────────────────
    // Limits
    // ──────────────────────────────────────────────────────

    /**
     * Get limit value for a feature
     * Priority: tenant override > plan feature
     *
     * Usage:
     *   Subscription::getLimit('max_posts')          → 10
     *   Subscription::getLimit('max_users')          → 5
     *   Subscription::getLimit('can_export_pdf')     → true/false
     */
    public static function getLimit(string $key, mixed $default = null): mixed
    {
        if (!config('cms.tenancy_enabled')) return $default;

        $tenantId = tenant('id');

        // ── Check tenant override limits ───────────────────
        // Extract module name from key (e.g. max_posts → check all overrides)
        $overrides = Cache::remember("tenant_{$tenantId}_overrides", 3600, function () use ($tenantId) {
            return DB::connection('central')
                ->table('tenant_module_access')
                ->where('tenant_id', $tenantId)
                ->where('is_enabled', true)
                ->get(['module_name', 'custom_limits']);
        });

        foreach ($overrides as $override) {
            if ($override->custom_limits) {
                $limits = json_decode($override->custom_limits, true);
                if (isset($limits[$key])) {
                    return $limits[$key];
                }
            }
        }

        // ── Check plan features ────────────────────────────
        $plan = self::getPlan();
        if (!$plan) return $default;

        $feature = DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $plan->id)
            ->where('feature_key', $key)
            ->first();

        if (!$feature) return $default;

        // Auto-cast values
        $value = $feature->feature_value;
        if ($value === 'true')  return true;
        if ($value === 'false') return false;
        if (is_numeric($value)) return (int) $value;

        return $value;
    }

    /**
     * Check if tenant can add more records
     *
     * Usage:
     *   Subscription::canCreate('posts', DB::table('posts')->count())
     */
    public static function canCreate(string $limitKey, int $currentCount): bool
    {
        if (!config('cms.tenancy_enabled')) return true;

        $limit = self::getLimit('max_' . $limitKey);

        if ($limit === null || $limit === -1) return true; // unlimited
        return $currentCount < (int) $limit;
    }

    /**
     * Check feature flag
     *
     * Usage:
     *   Subscription::hasFeature('can_export_pdf')
     *   Subscription::hasFeature('can_bulk_import')
     */
    public static function hasFeature(string $feature): bool
    {
        if (!config('cms.tenancy_enabled')) return true;
        return (bool) self::getLimit($feature, false);
    }

    /**
     * Check if tenant can add more users
     */
    public static function canAddUser(): bool
    {
        if (!config('cms.tenancy_enabled')) return true;

        $maxUsers = self::getLimit('max_users', -1);
        if ($maxUsers === -1) return true;

        $currentUsers = DB::table('users')->count();
        return $currentUsers < $maxUsers;
    }

    // ──────────────────────────────────────────────────────
    // Tenant overrides
    // ──────────────────────────────────────────────────────

    /**
     * Get tenant override for a module
     */
    public static function getTenantOverride(string $tenantId, string $module): ?object
    {
        return Cache::remember(
            "tenant_{$tenantId}_override_{$module}",
            3600,
            function () use ($tenantId, $module) {
                return DB::connection('central')
                    ->table('tenant_module_access')
                    ->where('tenant_id', $tenantId)
                    ->where('module_name', $module)
                    ->first();
            }
        );
    }

    /**
     * Get all module overrides for current tenant
     */
    public static function getTenantOverrides(): array
    {
        if (!config('cms.tenancy_enabled')) return [];

        $tenantId = tenant('id');
        if (!$tenantId) return [];

        return DB::connection('central')
            ->table('tenant_module_access')
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('module_name')
            ->toArray();
    }

    // ──────────────────────────────────────────────────────
    // Info helpers
    // ──────────────────────────────────────────────────────

    public static function getPlanName(): string
    {
        $plan = self::getPlan();
        return $plan ? $plan->name : 'No Plan';
    }

    public static function getDaysRemaining(): ?int
    {
        $plan = self::getPlan();
        if (!$plan || !$plan->subscription_ends_at) return null;
        return now()->diffInDays($plan->subscription_ends_at, false);
    }

    // ──────────────────────────────────────────────────────
    // Cache
    // ──────────────────────────────────────────────────────

    public static function clearCache(): void
    {
        $tenantId = tenant('id') ?? request()->route('tenant');
        if (!$tenantId) return;

        // Clear all tenant cache keys
        $modules = DB::connection('central')
            ->table('tenant_module_access')
            ->where('tenant_id', $tenantId)
            ->pluck('module_name');

        Cache::forget("tenant_{$tenantId}_plan");
        Cache::forget("tenant_{$tenantId}_overrides");

        foreach ($modules as $module) {
            Cache::forget("tenant_{$tenantId}_override_{$module}");
        }
    }

    /**
     * Clear cache for specific tenant (called from central admin)
     */
    public static function clearCacheForTenant(string $tenantId): void
    {
        Cache::forget("tenant_{$tenantId}_plan");
        Cache::forget("tenant_{$tenantId}_overrides");
    }
}
