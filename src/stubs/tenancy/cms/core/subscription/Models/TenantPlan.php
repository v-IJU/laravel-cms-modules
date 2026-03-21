<?php

namespace cms\core\subscription\Models;

use Illuminate\Database\Eloquent\Model;

class TenantPlan extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'trial_ends_at'  => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->ends_at && now()->gt($this->ends_at)) return false;
        return true;
    }
}
