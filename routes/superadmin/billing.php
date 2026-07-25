<?php

use App\Http\Controllers\Admin\BillingResourceController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::redirect('billing/plans', '/superadmin/plans')->name('billing.plans.index');
Route::redirect('billing/subscriptions', '/superadmin/subscriptions')->name('billing.subscriptions.index');
Route::resource('plans', PlanController::class)->middleware('central.permission:plans.view');
Route::resource('subscriptions', SubscriptionController::class)->only(['index', 'show', 'update'])->middleware('central.permission:subscriptions.view');
Route::get('billing/{resource}', BillingResourceController::class)
    ->whereIn('resource', ['payments', 'invoices', 'refunds', 'coupons', 'taxes', 'currencies', 'gateways'])
    ->middleware('central.permission:payments.view')
    ->name('billing.resource.index');
