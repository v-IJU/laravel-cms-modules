<?php

namespace cms\core\subscription\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'max_users',
        'max_modules',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price'     => 'decimal:2',
    ];

    public function features()
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function tenantPlans()
    {
        return $this->hasMany(TenantPlan::class);
    }

    public function hasFeature(string $key): bool
    {
        return $this->features()
                    ->where('feature_key', $key)
                    ->where('feature_value', 'true')
                    ->exists();
    }
}
