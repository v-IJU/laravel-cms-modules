<?php

use Illuminate\Support\Facades\Route;
use cms\core\subscription\Controllers\PlanController;
use cms\core\subscription\Controllers\SubscriptionController;

Route::prefix('administrator/subscription')
    ->middleware(['web', 'auth'])
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

        Route::post('/{tenant}/cancel', [SubscriptionController::class, 'cancel'])
             ->name('subscription.cancel');
    });
