<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ConsolePageController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

Route::redirect('/admin', '/superadmin');
Route::redirect('/admin/dashboard', '/superadmin/dashboard');
Route::get('/admin/{path}', fn (string $path) => redirect('/superadmin/'.$path))
    ->where('path', '.*');

Route::middleware(['central.domain', 'auth:central'])->prefix('superadmin')->name('superadmin.')->group(function (): void {
    Route::redirect('/', '/superadmin/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::redirect('billing/plans', '/superadmin/plans')->name('billing.plans.index');
    Route::redirect('billing/subscriptions', '/superadmin/subscriptions')->name('billing.subscriptions.index');
    Route::get('billing/payments', [ConsolePageController::class, 'payments'])->name('billing.payments.index');
    Route::get('billing/invoices', [ConsolePageController::class, 'invoices'])->name('billing.invoices.index');
    Route::get('billing/coupons', [ConsolePageController::class, 'coupons'])->name('billing.coupons.index');
    Route::get('billing/gateways', [ConsolePageController::class, 'gateways'])->name('billing.gateways.index');
    Route::get('platform/usage', [ConsolePageController::class, 'usage'])->name('platform.usage.index');
    Route::get('platform/integrations', [ConsolePageController::class, 'integrations'])->name('platform.integrations.index');
    Route::get('website', [ConsolePageController::class, 'website'])->name('website.index');
    Route::get('communications', [ConsolePageController::class, 'communications'])->name('communications.index');
    Route::get('support', [ConsolePageController::class, 'support'])->name('support.index');
    Route::get('operations/health', [ConsolePageController::class, 'operations'])->name('operations.health');
    Route::get('system/audit-logs', [ConsolePageController::class, 'auditLogs'])->name('system.audit-logs.index');
    Route::get('system/login-attempts', [ConsolePageController::class, 'loginAttempts'])->name('system.login-attempts.index');
    Route::get('system/administrators', [ConsolePageController::class, 'administrators'])->name('system.administrators.index');
    Route::get('system/roles', [ConsolePageController::class, 'roles'])->name('system.roles.index');
    Route::get('system/settings', [ConsolePageController::class, 'settings'])->name('system.settings.index');
    Route::get('system/security', [ConsolePageController::class, 'security'])->name('system.security.index');

    Route::resource('plans', PlanController::class);
    Route::resource('features', FeatureController::class);
    Route::resource('subscriptions', SubscriptionController::class)->only(['index', 'show', 'update']);
    Route::post('tenants/{tenant}/retry', [TenantController::class, 'retry'])->name('tenants.retry');
    Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('tenants.activate');
    Route::post('tenants/{tenant}/migrate', [TenantController::class, 'migrate'])->name('tenants.migrate');
    Route::post('tenants/{tenant}/seed', [TenantController::class, 'seed'])->name('tenants.seed');
    Route::resource('tenants', TenantController::class);
});
