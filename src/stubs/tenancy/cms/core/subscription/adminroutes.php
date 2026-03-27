<?php

use Illuminate\Support\Facades\Route;
use cms\core\subscription\Controllers\PlanController;
use cms\core\subscription\Controllers\SubscriptionController;

Route::prefix('administrator/subscription')
     ->middleware(['web', 'Admin','subscription.gate:subscription'])
     ->group(function () {

          // ── Plans ──────────────────────────────────────────
          Route::resource('plans', PlanController::class)
               ->names('subscription.plans');

          // ── Subscriptions ──────────────────────────────────
          Route::get('/', [SubscriptionController::class, 'index'])
               ->name('subscription.index');

          Route::post('/{tenant}/assign', [SubscriptionController::class, 'assign'])
               ->name('subscription.assign');

          Route::post('/{tenant}/suspend', [SubscriptionController::class, 'suspend'])
               ->name('subscription.suspend');

          Route::post('/{tenant}/reactivate', [SubscriptionController::class, 'reactivate'])
               ->name('subscription.reactivate');

          Route::post('/{tenant}/cancel', [SubscriptionController::class, 'cancel'])
               ->name('subscription.cancel');
     });


Route::prefix('administrator/tenants')
     ->middleware(['web', 'Admin','subscription.gate:subscription'])
     ->group(function () {

          Route::get('/',                 [\cms\core\subscription\Controllers\TenantController::class, 'index'])
               ->name('tenants.index');

          Route::get('/create',           [\cms\core\subscription\Controllers\TenantController::class, 'create'])
               ->name('tenants.create');

          Route::post('/',                [\cms\core\subscription\Controllers\TenantController::class, 'store'])
               ->name('tenants.store');

          Route::get('/{tenant}',         [\cms\core\subscription\Controllers\TenantController::class, 'show'])
               ->name('tenants.show');

          Route::get('/{tenant}/onboard', [\cms\core\subscription\Controllers\TenantController::class, 'onboard'])
               ->name('tenants.onboard');

          Route::post('/{tenant}/approve', [\cms\core\subscription\Controllers\TenantController::class, 'approve'])
               ->name('tenants.approve');

          Route::post('/{tenant}/suspend', [\cms\core\subscription\Controllers\TenantController::class, 'suspend'])
               ->name('tenants.suspend');

          Route::post('/{tenant}/reactivate', [\cms\core\subscription\Controllers\TenantController::class, 'reactivate'])
               ->name('tenants.reactivate');

          Route::post('/{tenant}/reject', [\cms\core\subscription\Controllers\TenantController::class, 'reject'])
               ->name('tenants.reject');
     });

// ── Upgrade page ───────────────────────────────────────
Route::get('/administrator/upgrade', function () {
     $plan  = \cms\core\subscription\helpers\Subscription::getPlan();
      $plans = \cms\core\subscription\Models\Plan::on('central')
        ->where('is_active', 1)
        ->orderBy('order')
        ->get();
     return view('subscription::admin.upgrade', compact('plan', 'plans'));
})->middleware(['web', 'Admin'])->name('subscription.upgrade');


// ── Tenant module overrides ────────────────────────────
Route::prefix('administrator/tenants')
     ->middleware(['web', 'Admin','subscription.gate:subscription'])
     ->group(function () {
          Route::get(
               '/{tenant}/overrides',
               [\cms\core\subscription\Controllers\TenantController::class, 'overrides']
          )
               ->name('tenants.overrides');

          Route::post(
               '/{tenant}/overrides',
               [\cms\core\subscription\Controllers\TenantController::class, 'saveOverride']
          )
               ->name('tenants.override.save');

          Route::delete(
               '/{tenant}/overrides/{module}',
               [\cms\core\subscription\Controllers\TenantController::class, 'deleteOverride']
          )
               ->name('tenants.override.delete');
     });
