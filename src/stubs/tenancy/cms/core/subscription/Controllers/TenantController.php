<?php

namespace cms\core\subscription\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Tenant;
use cms\core\subscription\Models\Plan;

class TenantController extends Controller
{
    // ── Tenant list ────────────────────────────────────
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $tenants = DB::table('tenants')
                ->leftJoin('tenant_plans', function ($join) {
                    $join->on('tenants.id', '=', 'tenant_plans.tenant_id')
                        ->whereIn('tenant_plans.status', ['active', 'trial']);
                })
                ->leftJoin('plans', 'plans.id', '=', 'tenant_plans.plan_id')
                ->select(
                    'tenants.id',
                    'tenants.name',
                    'tenants.email',
                    'tenants.status',
                    'tenants.onboard_status',
                    'tenants.trial_ends_at',
                    'tenants.created_at',
                    'plans.name as plan_name',
                    'tenant_plans.ends_at as sub_ends_at'
                )
                ->orderBy('tenants.created_at', 'desc');

            return DataTables::of($tenants)
                ->addIndexColumn()
                ->addColumn('onboard_badge', function ($t) {
                    return match ($t->onboard_status ?? 'trial') {
                        'trial'    => '<span class="badge bg-info">Trial</span>',
                        'pending'  => '<span class="badge bg-warning">Pending Approval</span>',
                        'active'   => '<span class="badge bg-success">Active</span>',
                        'rejected' => '<span class="badge bg-danger">Rejected</span>',
                        'suspended' => '<span class="badge bg-secondary">Suspended</span>',
                        default    => '<span class="badge bg-light text-dark">' . $t->onboard_status . '</span>',
                    };
                })
                ->addColumn('trial_left', function ($t) {
                    if (!$t->trial_ends_at) return '—';
                    $days = now()->diffInDays($t->trial_ends_at, false);
                    if ($days < 0) return '<span class="text-danger">Expired</span>';
                    if ($days <= 3) return '<span class="text-warning">' . $days . 'd left</span>';
                    return '<span class="text-success">' . $days . 'd left</span>';
                })
                ->addColumn('action', function ($t) {
                    $actions = '
                    <div class="d-flex gap-1 flex-wrap">
                        <a href="' . route('tenants.show', $t->id) . '"
                           class="btn btn-sm btn-info" title="View">
                            <i class="bx bx-show"></i>
                        </a>';

                    if (in_array($t->onboard_status, ['trial', 'pending'])) {
                        $actions .= '
                        <a href="' . route('tenants.onboard', $t->id) . '"
                           class="btn btn-sm btn-success" title="Approve & Onboard">
                            <i class="bx bx-check-circle"></i>
                        </a>';
                    }

                    if ($t->onboard_status === 'active') {
                        $actions .= '
                        <form action="' . route('tenants.suspend', $t->id) . '"
                              method="POST" class="d-inline">
                            ' . csrf_field() . '
                            <button class="btn btn-sm btn-warning" title="Suspend">
                                <i class="bx bx-pause"></i>
                            </button>
                        </form>';
                    }

                    if ($t->onboard_status === 'suspended') {
                        $actions .= '
                        <form action="' . route('tenants.reactivate', $t->id) . '"
                              method="POST" class="d-inline">
                            ' . csrf_field() . '
                            <button class="btn btn-sm btn-success" title="Reactivate">
                                <i class="bx bx-play"></i>
                            </button>
                        </form>';
                    }

                    if (!in_array($t->onboard_status, ['rejected'])) {
                        $actions .= '
                        <form action="' . route('tenants.reject', $t->id) . '"
                              method="POST" class="d-inline reject-form">
                            ' . csrf_field() . '
                            <button class="btn btn-sm btn-danger" title="Reject">
                                <i class="bx bx-x-circle"></i>
                            </button>
                        </form>';
                    }

                    $actions .= '</div>';
                    return $actions;
                })
                ->rawColumns(['onboard_badge', 'trial_left', 'action'])
                ->make(true);
        }

        $plans = Plan::where('is_active', 1)->orderBy('order')->get();
        return view('subscription::admin.tenants.index', compact('plans'));
    }

    // ── Create tenant form ─────────────────────────────
    public function create()
    {
        $plans = Plan::where('is_active', 1)->orderBy('order')->get();
        return view('subscription::admin.tenants.create', compact('plans'));
    }

    // ── Store new tenant ───────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'id'         => 'required|string|alpha_dash|unique:tenants,id|max:50',
            'name'       => 'required|string|max:100',
            'email'      => 'required|email',
            'plan_id'    => 'required|exists:plans,id',
            'trial_days' => 'required|integer|min:0',
        ]);

        try {
            $plan      = Plan::find($request->plan_id);
            $trialDays = (int) $request->trial_days;
            $trialEnds = $trialDays > 0 ? now()->addDays($trialDays) : null;

            // ── Create tenant (fires CreateDatabase job) ───
            $tenant = Tenant::create([
                'id'             => $request->id,
                'name'           => $request->name,
                'email'          => $request->email,
                'plan_id'        => $request->plan_id,
                'status'         => 'active',
                'onboard_status' => $trialDays > 0 ? 'trial' : 'pending',
                'trial_ends_at'  => $trialEnds,
            ]);

            // ── Add subdomain ──────────────────────────────
            $appDomain = env('APP_DOMAIN', 'localhost');
            $tenant->domains()->create([
                'domain' => $request->id . '.' . $appDomain
            ]);

            // ── Assign plan ────────────────────────────────
            DB::connection('central')->table('tenant_plans')->insert([
                'tenant_id'     => $request->id,
                'plan_id'       => $request->plan_id,
                'status'        => $trialDays > 0 ? 'trial' : 'active',
                'starts_at'     => now(),
                'ends_at'       => $trialDays > 0
                    ? $trialEnds
                    : match ($plan->billing_cycle) {
                        'monthly'  => now()->addMonth(),
                        'yearly'   => now()->addYear(),
                        'weekly'   => now()->addWeek(),
                        'lifetime' => null,
                        default    => now()->addMonth(),
                    },
                'trial_ends_at' => $trialEnds,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // ── Setup tenant DB ────────────────────────────
            $this->setupTenantDB($tenant, $request->plan_id);

            return redirect()
                ->route('tenants.show', $tenant->id)
                ->with('success', "Tenant [{$request->id}] created successfully!");
        } catch (\Exception $e) {
            tenancy()->end();
            return redirect()->back()
                ->with('error', 'Failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ── Tenant detail ──────────────────────────────────
    public function show(string $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);

        $domains = DB::connection('central')
            ->table('domains')
            ->where('tenant_id', $tenantId)
            ->get();

        $subscriptions = DB::connection('central')
            ->table('tenant_plans')
            ->join('plans', 'plans.id', '=', 'tenant_plans.plan_id')
            ->where('tenant_plans.tenant_id', $tenantId)
            ->select('tenant_plans.*', 'plans.name as plan_name', 'plans.price')
            ->orderBy('tenant_plans.created_at', 'desc')
            ->get();

        $plans = Plan::where('is_active', 1)->orderBy('order')->get();

        return view(
            'subscription::admin.tenants.show',
            compact('tenant', 'domains', 'subscriptions', 'plans')
        );
    }

    // ── Onboard form ───────────────────────────────────
    public function onboard(string $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        $plans  = Plan::where('is_active', 1)->orderBy('order')->get();

        return view(
            'subscription::admin.tenants.onboard',
            compact('tenant', 'plans')
        );
    }

    // ── Approve & activate ─────────────────────────────
    public function approve(Request $request, string $tenantId)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $tenant = Tenant::findOrFail($tenantId);
        $plan   = Plan::find($request->plan_id);

        $endsAt = match ($plan->billing_cycle) {
            'monthly'  => now()->addMonth(),
            'yearly'   => now()->addYear(),
            'weekly'   => now()->addWeek(),
            'lifetime' => null,
            default    => now()->addMonth(),
        };

        // ── Cancel trial plan ──────────────────────────
        DB::connection('central')
            ->table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        // ── Assign production plan ─────────────────────
        DB::connection('central')->table('tenant_plans')->insert([
            'tenant_id'  => $tenantId,
            'plan_id'    => $request->plan_id,
            'status'     => 'active',
            'starts_at'  => now(),
            'ends_at'    => $endsAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── Update tenant status ───────────────────────
        DB::connection('central')
            ->table('tenants')
            ->where('id', $tenantId)
            ->update([
                'onboard_status' => 'active',
                'plan_id'        => $request->plan_id,
                'approved_by'    => auth()->id(),
                'approved_at'    => now(),
                'updated_at'     => now(),
            ]);

        // ── Update modules based on new plan ──────────
        $allowedModules = $this->getAllowedModules($request->plan_id);

        tenancy()->initialize($tenant);

        Artisan::call('update:cms-module', [
            '--modules'        => $allowedModules === ['*'] ? [] : $allowedModules,
            '--no-interaction' => true,
        ]);

        tenancy()->end();
        tenancy()->initialize($tenant);

        Artisan::call('update:cms-menu', [
            '--modules'        => $allowedModules === ['*'] ? [] : $allowedModules,
            '--no-interaction' => true,
        ]);

        tenancy()->end();

        return redirect()
            ->route('tenants.show', $tenantId)
            ->with('success', "Tenant [{$tenantId}] approved and activated!");
    }

    // ── Suspend tenant ─────────────────────────────────
    public function suspend(string $tenantId)
    {
        DB::connection('central')
            ->table('tenants')
            ->where('id', $tenantId)
            ->update(['onboard_status' => 'suspended', 'updated_at' => now()]);

        DB::connection('central')
            ->table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'suspended', 'updated_at' => now()]);

        return redirect()
            ->route('tenants.index')
            ->with('success', "Tenant [{$tenantId}] suspended!");
    }

    // ── Reactivate tenant ──────────────────────────────
    public function reactivate(string $tenantId)
    {
        DB::connection('central')
            ->table('tenants')
            ->where('id', $tenantId)
            ->update(['onboard_status' => 'active', 'updated_at' => now()]);

        DB::connection('central')
            ->table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->where('status', 'suspended')
            ->update(['status' => 'active', 'updated_at' => now()]);

        return redirect()
            ->route('tenants.index')
            ->with('success', "Tenant [{$tenantId}] reactivated!");
    }

    // ── Reject tenant ──────────────────────────────────
    public function reject(Request $request, string $tenantId)
    {
        DB::connection('central')
            ->table('tenants')
            ->where('id', $tenantId)
            ->update([
                'onboard_status' => 'rejected',
                'onboard_notes'  => $request->reason ?? null,
                'updated_at'     => now(),
            ]);

        DB::connection('central')
            ->table('tenant_plans')
            ->where('tenant_id', $tenantId)
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        return redirect()
            ->route('tenants.index')
            ->with('success', "Tenant [{$tenantId}] rejected!");
    }

    // ── Setup tenant DB ────────────────────────────────
    protected function setupTenantDB(Tenant $tenant, int $planId): void
    {
        if (tenancy()->initialized) tenancy()->end();

        $tenant = Tenant::find($tenant->id);
        tenancy()->initialize($tenant);

        Artisan::call('cms-migrate', [
            '--db'             => 'tenant',
            '--no-interaction' => true,
        ]);

        tenancy()->end();
        tenancy()->initialize(Tenant::find($tenant->id));

        $allowedModules = $this->getAllowedModules($planId);

        Artisan::call('update:cms-module', [
            '--modules'        => $allowedModules === ['*'] ? [] : $allowedModules,
            '--no-interaction' => true,
        ]);

        tenancy()->end();
        tenancy()->initialize(Tenant::find($tenant->id));

        Artisan::call('update:cms-menu', [
            '--modules'        => $allowedModules === ['*'] ? [] : $allowedModules,
            '--no-interaction' => true,
        ]);

        tenancy()->end();
        tenancy()->initialize(Tenant::find($tenant->id));

        // Seed defaults
        DB::table('user_groups')->insert([
            ['group' => 'Super Admin', 'status' => 1],
            ['group' => 'Staff',       'status' => 1],
        ]);

        $adminGroupId = DB::table('user_groups')
            ->where('group', 'Super Admin')->value('id');

        $adminUserId = DB::table('users')->insertGetId([
            'name'       => 'Admin',
            'username'   => 'admin',
            'email'      => 'admin@' . $tenant->id . '.com',
            'password'   => Hash::make('admin123'),
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_group_map')->insert([
            'user_id'  => $adminUserId,
            'group_id' => $adminGroupId,
        ]);

        DB::table('configurations')->insert([
            [
                'name' => 'site',
                'parm' => json_encode([
                    'site_name' => $tenant->name,
                    'theme'     => 'theme1',
                    'timezone'  => 'UTC',
                ]),
            ],
        ]);

        tenancy()->end();
    }

    // ── Get allowed modules from plan ──────────────────
    protected function getAllowedModules(int $planId): array
    {
        $hasAll = DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $planId)
            ->where('feature_key', 'module_all')
            ->where('feature_value', 'true')
            ->exists();

        if ($hasAll) return ['*'];

        $modules = DB::connection('central')
            ->table('plan_features')
            ->where('plan_id', $planId)
            ->where('feature_value', 'true')
            ->where('feature_key', 'like', 'module_%')
            ->pluck('feature_key')
            ->map(fn($k) => str_replace('module_', '', $k))
            ->values()
            ->toArray();

        return empty($modules) ? ['user', 'menu', 'configurations'] : $modules;
    }

    // ── Manage module overrides ────────────────────────────
    public function overrides(string $tenantId)
    {
        $tenant   = Tenant::findOrFail($tenantId);

        $overrides = DB::connection('central')
            ->table('tenant_module_access')
            ->where('tenant_id', $tenantId)
            ->get();

        // All available modules
        $allModules = DB::table('modules')
            ->whereNotIn('name', ['subscription', 'layout', 'admin'])
            ->get(['id', 'name', 'type']);

        return view(
            'subscription::admin.tenants.overrides',
            compact('tenant', 'overrides', 'allModules')
        );
    }

    public function saveOverride(Request $request, string $tenantId)
    {
        $request->validate([
            'module_name'   => 'required|string',
            'is_enabled'    => 'required|boolean',
            'custom_limits' => 'nullable|json',
        ]);

        // Upsert override
        DB::connection('central')
            ->table('tenant_module_access')
            ->updateOrInsert(
                [
                    'tenant_id'   => $tenantId,
                    'module_name' => $request->module_name,
                ],
                [
                    'is_enabled'    => $request->is_enabled,
                    'custom_limits' => $request->custom_limits,
                    'notes'         => $request->notes,
                    'granted_by'    => auth()->id(),
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );

        // Clear cache for this tenant
        \cms\core\subscription\helpers\Subscription::clearCacheForTenant($tenantId);

        // If enabling module → sync to tenant DB
        if ($request->is_enabled) {
            if (tenancy()->initialized) tenancy()->end();
            tenancy()->initialize(Tenant::find($tenantId));

            \Artisan::call('update:cms-module', [
                '--modules'        => [$request->module_name],
                '--no-interaction' => true,
            ]);

            tenancy()->end();
            tenancy()->initialize(Tenant::find($tenantId));

            \Artisan::call('update:cms-menu', [
                '--modules'        => [$request->module_name],
                '--no-interaction' => true,
            ]);

            tenancy()->end();
        }

        return redirect()
            ->route('tenants.overrides', $tenantId)
            ->with('success', "Override saved for [{$tenantId}]!");
    }

    public function deleteOverride(string $tenantId, string $module)
    {
        DB::connection('central')
            ->table('tenant_module_access')
            ->where('tenant_id', $tenantId)
            ->where('module_name', $module)
            ->delete();

        \cms\core\subscription\helpers\Subscription::clearCacheForTenant($tenantId);

        return redirect()
            ->route('tenants.overrides', $tenantId)
            ->with('success', "Override removed!");
    }
}
