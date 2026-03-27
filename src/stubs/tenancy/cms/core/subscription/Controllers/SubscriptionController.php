<?php

namespace cms\core\subscription\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use cms\core\subscription\Models\Plan;
use cms\core\subscription\Models\TenantPlan;
use cms\core\subscription\helpers\Subscription;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $subscriptions = DB::table('tenant_plans')
                ->join('plans', 'plans.id', '=', 'tenant_plans.plan_id')
                ->join('tenants', 'tenants.id', '=', 'tenant_plans.tenant_id')
                ->select(
                    'tenant_plans.id',
                    'tenant_plans.tenant_id',
                    'tenant_plans.plan_id',
                    'tenant_plans.status',
                    'tenant_plans.starts_at',
                    'tenant_plans.ends_at',
                    'tenant_plans.trial_ends_at',
                    'tenant_plans.created_at',
                    'plans.name as plan_name',
                    'plans.price',
                    'plans.billing_cycle',
                    "tenants.name as tenant_name",
                    "tenants.email as tenant_email",
                  
                )
                ->orderBy('tenant_plans.created_at', 'desc');

            return DataTables::of($subscriptions)
                ->addIndexColumn()
                ->addColumn('status_badge', function ($sub) {
                    return match ($sub->status) {
                        'active'    => '<span class="badge bg-success">Active</span>',
                        'trial'     => '<span class="badge bg-info">Trial</span>',
                        'suspended' => '<span class="badge bg-warning">Suspended</span>',
                        'cancelled' => '<span class="badge bg-danger">Cancelled</span>',
                        default     => '<span class="badge bg-secondary">' . $sub->status . '</span>',
                    };
                })
                ->addColumn('ends_at_label', function ($sub) {
                    if (!$sub->ends_at) return '<span class="text-success">Never</span>';
                    $date    = \Carbon\Carbon::parse($sub->ends_at);
                    $days    = now()->diffInDays($date, false);
                    $color   = $days < 0 ? 'danger' : ($days < 7 ? 'warning' : 'success');
                    return '<span class="text-' . $color . '">'
                        . $date->format('Y-m-d')
                        . ($days >= 0 ? " ({$days}d)" : ' (expired)')
                        . '</span>';
                })
                ->addColumn('action', function ($sub) {
                    $plans = Plan::where('is_active', 1)->get();
                    $planOptions = '';
                    foreach ($plans as $p) {
                        $planOptions .= "<option value='{$p->id}'>{$p->name}</option>";
                    }

                    $actions = '
                    <div class="d-flex gap-1 flex-wrap">
                        <button class="btn btn-sm btn-info"
                            onclick="assignPlan(\'' . $sub->tenant_id . '\')"
                            title="Assign Plan">
                            <i class="bx bx-transfer"></i>
                        </button>';

                    if ($sub->status === 'active' || $sub->status === 'trial') {
                        $actions .= '
                        <form action="' . route('subscription.suspend', $sub->tenant_id) . '"
                              method="POST" class="d-inline">
                            ' . csrf_field() . '
                            <button class="btn btn-sm btn-warning" title="Suspend">
                                <i class="bx bx-pause"></i>
                            </button>
                        </form>';
                    }

                    if ($sub->status === 'suspended') {
                        $actions .= '
                        <form action="' . route('subscription.reactivate', $sub->tenant_id) . '"
                              method="POST" class="d-inline">
                            ' . csrf_field() . '
                            <button class="btn btn-sm btn-success" title="Reactivate">
                                <i class="bx bx-play"></i>
                            </button>
                        </form>';
                    }

                    if ($sub->status !== 'cancelled') {
                        $actions .= '
                        <form action="' . route('subscription.cancel', $sub->tenant_id) . '"
                              method="POST" class="d-inline cancel-form">
                            ' . csrf_field() . '
                            <button class="btn btn-sm btn-danger" title="Cancel">
                                <i class="bx bx-x"></i>
                            </button>
                        </form>';
                    }

                    $actions .= '</div>';
                    return $actions;
                })
                ->rawColumns(['status_badge', 'ends_at_label', 'action'])
                ->make(true);
        }

        $plans = Plan::where('is_active', 1)->orderBy('order')->get();
        return view('subscription::admin.subscriptions.index', compact('plans'));
    }

    public function assign(Request $request, string $tenantId)
    {
       // dd($request->all());
        $request->validate([
            'plan_id'   => 'required|exists:plans,id',
            'starts_at' => 'required|date',
            'ends_at'   => 'nullable|date|after:starts_at',
        ]);

        $plan = Plan::find($request->plan_id);

        // Calculate end date from billing cycle if not provided
        $endsAt = $request->ends_at ?? match ($plan->billing_cycle) {
            'monthly'  => now()->addMonth(),
            'yearly'   => now()->addYear(),
            'weekly'   => now()->addWeek(),
            'lifetime' => null,
            default    => now()->addMonth(),
        };

        // Cancel existing active plans
        DB::table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        // Assign new plan
        DB::table('tenant_plans')->insert([
            'tenant_id'  => $tenantId,
            'plan_id'    => $request->plan_id,
            'status'     => 'active',
            'starts_at'  => $request->starts_at,
            'ends_at'    => $endsAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Clear cache
        Subscription::clearCache();

        return redirect()
            ->route('subscription.index')
            ->with('success', "Plan assigned to [{$tenantId}]!");
    }

    public function suspend(string $tenantId)
    {
        DB::table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'suspended', 'updated_at' => now()]);

        Subscription::clearCache();

        return redirect()
            ->route('subscription.index')
            ->with('success', "Tenant [{$tenantId}] suspended!");
    }

    public function reactivate(string $tenantId)
    {
        DB::table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->where('status', 'suspended')
            ->update(['status' => 'active', 'updated_at' => now()]);

        Subscription::clearCache();

        return redirect()
            ->route('subscription.index')
            ->with('success', "Tenant [{$tenantId}] reactivated!");
    }

    public function cancel(string $tenantId)
    {
        DB::table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled'])
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        Subscription::clearCache();

        return redirect()
            ->route('subscription.index')
            ->with('success', "Tenant [{$tenantId}] subscription cancelled!");
    }
}
