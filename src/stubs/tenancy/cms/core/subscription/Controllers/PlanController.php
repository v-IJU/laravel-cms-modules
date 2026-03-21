<?php

namespace cms\core\subscription\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use cms\core\subscription\Models\Plan;
use cms\core\subscription\Models\PlanFeature;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::orderBy('order')->get();
        return view('subscription::admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('subscription::admin.plans.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'slug'          => 'required|string|unique:plans,slug',
            'price'         => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly',
            'max_users'     => 'required|integer|min:-1',
            'max_modules'   => 'required|integer|min:-1',
        ]);

        $plan = Plan::create($request->only([
            'name', 'slug', 'description', 'price',
            'billing_cycle', 'max_users', 'max_modules',
            'is_active', 'order',
        ]));

        // Save features
        if ($request->has('features')) {
            foreach ($request->features as $key => $value) {
                PlanFeature::create([
                    'plan_id'       => $plan->id,
                    'feature_key'   => $key,
                    'feature_value' => $value,
                ]);
            }
        }

        return redirect()
            ->route('subscription.plans.index')
            ->with('success', 'Plan created successfully!');
    }

    public function edit(Plan $plan)
    {
        $features = $plan->features->pluck('feature_value', 'feature_key');
        return view('subscription::admin.plans.edit', compact('plan', 'features'));
    }

    public function update(Request $request, Plan $plan)
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'price'         => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly',
            'max_users'     => 'required|integer|min:-1',
            'max_modules'   => 'required|integer|min:-1',
        ]);

        $plan->update($request->only([
            'name', 'description', 'price',
            'billing_cycle', 'max_users', 'max_modules',
            'is_active', 'order',
        ]));

        // Update features
        $plan->features()->delete();
        if ($request->has('features')) {
            foreach ($request->features as $key => $value) {
                PlanFeature::create([
                    'plan_id'       => $plan->id,
                    'feature_key'   => $key,
                    'feature_value' => $value,
                ]);
            }
        }

        return redirect()
            ->route('subscription.plans.index')
            ->with('success', 'Plan updated successfully!');
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();
        return redirect()
            ->route('subscription.plans.index')
            ->with('success', 'Plan deleted!');
    }
}
