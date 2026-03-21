<?php

namespace cms\core\subscription\Models;

use Illuminate\Database\Eloquent\Model;

class PlanFeature extends Model
{
    protected $fillable = [
        'plan_id',
        'feature_key',
        'feature_value',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
