<?php

namespace cms\core\subscription\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use cms\core\subscription\Models\Plan;
use cms\core\subscription\Models\TenantPlan;
use cms\core\subscription\helpers\Subscription;

class SubscriptionController extends Controller
{
    public function index()
    {
        $subscriptions = DB::table('tenant_plans')
            ->join('plans', 'plans.id', '=', 'tenant_plans.plan_id')
            ->join('tenants', 'tenants.id', '=', 'tenant_plans.tenant_id')
            ->select(
                'tenant_plans.*',
                'plans.name as plan_name',
                'plans.price',
                'tenants.id as tenant_id'
            )
            ->orderBy('tenant_plans.created_at', 'desc')
            ->paginate(20);

        return view('subscription::admin.subscriptions.index', compact('subscriptions'));
    }

    public function assign(Request $request, string $tenantId)
    {
        $request->validate([
            'plan_id'    => 'required|exists:plans,id',
            'starts_at'  => 'required|date',
            'ends_at'    => 'nullable|date|after:starts_at',
        ]);

        // Cancel existing
        DB::table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        // Assign new plan
        DB::table('tenant_plans')->insert([
            'tenant_id'  => $tenantId,
            'plan_id'    => $request->plan_id,
            'status'     => 'active',
            'starts_at'  => $request->starts_at,
            'ends_at'    => $request->ends_at,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Clear tenant plan cache
        Subscription::clearCache();

        return redirect()
            ->route('subscription.index')
            ->with('success', "Plan assigned to tenant [{$tenantId}]!");
    }

    public function suspend(string $tenantId)
    {
        DB::table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->update(['status' => 'suspended']);

        Subscription::clearCache();

        return redirect()
            ->route('subscription.index')
            ->with('success', "Tenant [{$tenantId}] suspended!");
    }

    public function cancel(string $tenantId)
    {
        DB::table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        Subscription::clearCache();

        return redirect()
            ->route('subscription.index')
            ->with('success', "Tenant [{$tenantId}] subscription cancelled!");
    }
}
