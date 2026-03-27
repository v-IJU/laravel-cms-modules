<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'plan_id',
            'status',
            'onboard_status',
            'trial_ends_at',
            'onboard_notes',
            'approved_by',
            'approved_at',
        ];
    }

    public function isTrialing(): bool
    {
        return $this->onboard_status === 'trial'
            && $this->trial_ends_at
            && now()->lt($this->trial_ends_at);
    }

    public function isActive(): bool
    {
        return $this->onboard_status === 'active';
    }

    public function trialDaysLeft(): int
    {
        if (!$this->trial_ends_at) return 0;
        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }
    public function currentPlan()
    {
        return \Illuminate\Support\Facades\DB::connection('central')
            ->table('tenant_plans')
            ->join('plans', 'plans.id', '=', 'tenant_plans.plan_id')
            ->where('tenant_plans.tenant_id', $this->id)
            ->whereIn('tenant_plans.status', ['active', 'trial'])
            ->select(
                'plans.*',
                'tenant_plans.status as sub_status',
                'tenant_plans.ends_at as sub_ends_at'
            )
            ->first();
    }
}
