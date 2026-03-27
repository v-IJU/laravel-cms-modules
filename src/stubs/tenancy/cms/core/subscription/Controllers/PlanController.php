<?php

namespace cms\core\subscription\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use cms\core\subscription\Models\Plan;
use cms\core\subscription\Models\PlanFeature;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $plans = Plan::withCount('tenantPlans')->orderBy('order');

            return DataTables::of($plans)
                ->addIndexColumn()
                ->addColumn('price_label', function ($plan) {
                    return '$' . number_format($plan->price, 2)
                        . ' / ' . $plan->billing_cycle;
                })
                ->addColumn('max_users_label', function ($plan) {
                    return $plan->max_users == -1 ? 'Unlimited' : $plan->max_users;
                })
                ->addColumn('max_modules_label', function ($plan) {
                    return $plan->max_modules == -1 ? 'Unlimited' : $plan->max_modules;
                })
                ->addColumn('status_badge', function ($plan) {
                    return $plan->is_active
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })
                ->addColumn('action', function ($plan) {
                    return '
                        <div class="d-flex gap-2">
                            <a href="' . route('subscription.plans.edit', $plan->id) . '"
                               class="btn btn-sm btn-primary">
                                <i class="bx bx-edit"></i>
                            </a>
                            <form action="' . route('subscription.plans.destroy', $plan->id) . '"
                                  method="POST" class="d-inline delete-form">
                                ' . csrf_field() . method_field('DELETE') . '
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </form>
                        </div>
                    ';
                })
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        return view('subscription::admin.plans.index');
    }

    public function create()
    {
        // Get all available modules for feature assignment
        $modules = DB::table('modules')
            ->where('status', 1)
            ->whereNotIn('name', ['subscription'])
            ->get(['id', 'name', 'type']);

        return view('subscription::admin.plans.create', compact('modules'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'slug'          => 'required|string|unique:plans,slug',
            'price'         => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly,weekly,lifetime',
            'max_users'     => 'required|integer|min:-1',
            'max_modules'   => 'required|integer|min:-1',
            'trial_days'    => 'nullable|integer|min:0',
        ]);

        $plan = Plan::create([
            'name'          => $request->name,
            'slug'          => $request->slug,
            'description'   => $request->description,
            'price'         => $request->price,
            'billing_cycle' => $request->billing_cycle,
            'max_users'     => $request->max_users,
            'max_modules'   => $request->max_modules,
            'trial_days'    => $request->trial_days ?? 0,
            'is_active'     => $request->has('is_active') ? 1 : 0,
            'order'         => $request->order ?? 0,
        ]);

        // Save module features
        if ($request->has('modules')) {
            foreach ($request->modules as $moduleId => $value) {
                $moduleName = DB::table('modules')->where('id', $moduleId)->value('name');
                if ($moduleName) {
                    PlanFeature::create([
                        'plan_id'       => $plan->id,
                        'feature_key'   => 'module_' . $moduleName,
                        'feature_value' => $value ? 'true' : 'false',
                    ]);
                }
            }
        }

        // Wildcard access
        if ($request->has('all_modules')) {
            PlanFeature::create([
                'plan_id'       => $plan->id,
                'feature_key'   => 'module_all',
                'feature_value' => 'true',
            ]);
        }

        return redirect()
            ->route('subscription.plans.index')
            ->with('success', 'Plan created successfully!');
    }

    public function edit(Plan $plan)
    {
        $modules  = DB::table('modules')
            ->where('status', 1)
            ->whereNotIn('name', ['subscription'])
            ->get(['id', 'name', 'type']);

        $features     = $plan->features->pluck('feature_value', 'feature_key');
        $hasAllAccess = $features->get('module_all') === 'true';

        return view(
            'subscription::admin.plans.edit',
            compact('plan', 'features', 'modules', 'hasAllAccess')
        );
    }

    public function update(Request $request, Plan $plan)
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'price'         => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly,weekly,lifetime',
            'max_users'     => 'required|integer|min:-1',
            'max_modules'   => 'required|integer|min:-1',
            'trial_days'    => 'nullable|integer|min:0',
        ]);

        // ── Get OLD modules before update ──────────────────
        $oldModules = $plan->features
            ->where('feature_value', 'true')
            ->where('feature_key', '!=', 'module_all')
            ->pluck('feature_key')
            ->map(fn($k) => str_replace('module_', '', $k))
            ->toArray();

        $hadAllAccess = $plan->features
            ->where('feature_key', 'module_all')
            ->where('feature_value', 'true')
            ->isNotEmpty();

        // ── Update plan ────────────────────────────────────
        $plan->update([
            'name'          => $request->name,
            'description'   => $request->description,
            'price'         => $request->price,
            'billing_cycle' => $request->billing_cycle,
            'max_users'     => $request->max_users,
            'max_modules'   => $request->max_modules,
            'trial_days'    => $request->trial_days ?? 0,
            'is_active'     => $request->has('is_active') ? 1 : 0,
            'order'         => $request->order ?? 0,
        ]);

        // ── Update features ────────────────────────────────
        $plan->features()->delete();

        if ($request->has('all_modules')) {
            PlanFeature::create([
                'plan_id'       => $plan->id,
                'feature_key'   => 'module_all',
                'feature_value' => 'true',
            ]);
            $newModules   = ['*'];
            $hasAllAccess = true;
        } elseif ($request->has('modules')) {
            foreach ($request->modules as $moduleId => $value) {
                $moduleName = DB::table('modules')->where('id', $moduleId)->value('name');
                if ($moduleName) {
                    PlanFeature::create([
                        'plan_id'       => $plan->id,
                        'feature_key'   => 'module_' . $moduleName,
                        'feature_value' => $value ? 'true' : 'false',
                    ]);
                }
            }
            $newModules   = collect($request->modules)
                ->filter(fn($v) => $v)
                ->keys()
                ->map(fn($id) => DB::table('modules')->where('id', $id)->value('name'))
                ->filter()
                ->values()
                ->toArray();
            $hasAllAccess = false;
        } else {
            $newModules   = [];
            $hasAllAccess = false;
        }

        // ── Find newly added modules ───────────────────────
        $addedModules = $hasAllAccess
            ? ['*']
            : array_diff($newModules, $oldModules);

        $totalModules=array_merge($newModules, $oldModules);

        // ── Sync tenants if modules changed ───────────────
        if (!empty($addedModules) || $hasAllAccess !== $hadAllAccess) {
            $this->syncTenantsForPlan($plan, $addedModules, $hasAllAccess,$totalModules);
        }

        return redirect()
            ->route('subscription.plans.index')
            ->with('success', 'Plan updated and tenants synced!');
    }

    // ──────────────────────────────────────────────────────
    // Sync all tenants on this plan
    // ──────────────────────────────────────────────────────
    protected function syncTenantsForPlan(
        Plan $plan,
        array $addedModules,
        bool $hasAllAccess,array $totalModules
    ): void {
       
        // Get all active tenants on this plan
        $tenantIds = DB::table('tenant_plans')
            ->where('plan_id', $plan->id)
            ->whereIn('status', ['active', 'trial'])
            ->pluck('tenant_id');

        if ($tenantIds->isEmpty()) return;

        $tenants = \App\Models\Tenant::whereIn('id', $tenantIds)->get();

        foreach ($tenants as $tenant) {
            try {
                if (tenancy()->initialized) tenancy()->end();
                tenancy()->initialize(\App\Models\Tenant::find($tenant->id));

                if ($hasAllAccess) {
                   
                    // All modules
                    \Artisan::call('update:cms-module', ['--no-interaction' => true]);
                    tenancy()->end();
                    tenancy()->initialize(\App\Models\Tenant::find($tenant->id));
                    \Artisan::call('update:cms-menu', ['--no-interaction' => true]);
                } elseif (!empty($addedModules)) {
                    $modules = array_values($addedModules);
                     
                    // Only newly added modules
                    \Artisan::call('update:cms-module', [
                        '--modules'        => $addedModules,
                        "--scope" => "tenant",
                        '--no-interaction' => true,
                    ]);
                    tenancy()->end();
                    tenancy()->initialize(\App\Models\Tenant::find($tenant->id));

                    \Artisan::call('update:cms-menu', [
                        '--modules'        => $totalModules,
                         '--scope' => "tenant",
                        '--no-interaction' => true,
                    ]);
                }

                // Clear subscription cache
                \cms\core\subscription\helpers\Subscription::clearCacheForTenant($tenant->id);
            } catch (\Exception $e) {
                
                \Illuminate\Support\Facades\Log::error(
                    "[CMS] Failed to sync tenant [{$tenant->id}]: " . $e->getMessage()
                );
            } finally {
                tenancy()->end();
            }
        }
    }


    public function destroy(Plan $plan)
    {
        //dd("yes");
        // Check if plan has active subscriptions
        $activeCount = $plan->tenantPlans()
            ->where('status', 'active')
            ->count();

        if ($activeCount > 0) {
            return redirect()
                ->route('subscription.plans.index')
                ->with('error', "Cannot delete — {$activeCount} active subscriptions!");
        }

        $plan->delete();

        return redirect()
            ->route('subscription.plans.index')
            ->with('success', 'Plan deleted!');
    }
}
