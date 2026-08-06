<?php

use App\Http\Controllers\Admin\ConsolePageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\WebsiteController;
use App\Http\Controllers\Admin\WebsitePageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/admin', '/superadmin');
Route::redirect('/admin/dashboard', '/superadmin/dashboard');
Route::get('/admin/{path}', fn (string $path) => redirect('/superadmin/'.$path))
    ->where('path', '.*');

Route::middleware(['central.domain', 'auth:central', 'central.active', 'central.password'])->prefix('superadmin')->name('superadmin.')->group(function (): void {
    Route::redirect('/', '/superadmin/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard')->middleware('permission:dashboard.view');

    Route::redirect('billing/plans', '/superadmin/plans')->name('billing.plans.index');
    Route::redirect('billing/subscriptions', '/superadmin/subscriptions')->name('billing.subscriptions.index');

    Route::get('billing/payments', [ConsolePageController::class, 'payments'])->name('billing.payments.index')->middleware('permission:payments.view');

    Route::prefix('billing/invoices')->name('billing.invoices.')->group(function (): void {
        Route::get('/', [InvoiceController::class, 'index'])->name('index')->middleware('permission:invoices.view');
        Route::get('/create', [InvoiceController::class, 'create'])->name('create')->middleware('permission:invoices.manage');
        Route::post('/', [InvoiceController::class, 'store'])->name('store')->middleware('permission:invoices.manage');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show')->middleware('permission:invoices.view');
        Route::post('/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('mark-paid')->middleware('permission:invoices.manage');
        Route::post('/{invoice}/void', [InvoiceController::class, 'void'])->name('void')->middleware('permission:invoices.manage');
    });

    Route::get('billing/coupons', [ConsolePageController::class, 'coupons'])->name('billing.coupons.index')->middleware('permission:coupons.view');
    Route::get('billing/gateways', [ConsolePageController::class, 'gateways'])->name('billing.gateways.index')->middleware('permission:gateways.manage');
    Route::get('platform/usage', [ConsolePageController::class, 'usage'])->name('platform.usage.index')->middleware('permission:usage.view');
    Route::get('platform/integrations', [ConsolePageController::class, 'integrations'])->name('platform.integrations.index')->middleware('permission:integrations.view');
    Route::prefix('website')->name('website.')->group(function (): void {
        Route::get('/', [WebsiteController::class, 'index'])->name('index')->middleware('permission:website.view');
        Route::put('/settings', [WebsiteController::class, 'updateSettings'])->name('settings.update')->middleware('permission:website.manage');
        Route::post('/media', [WebsiteController::class, 'uploadMedia'])->name('media.store')->middleware('permission:website.manage');

        Route::post('/navigation', [WebsiteController::class, 'storeNavigationItem'])->name('navigation.store')->middleware('permission:website.manage');
        Route::put('/navigation/{item}', [WebsiteController::class, 'updateNavigationItem'])->name('navigation.update')->middleware('permission:website.manage');
        Route::delete('/navigation/{item}', [WebsiteController::class, 'destroyNavigationItem'])->name('navigation.destroy')->middleware('permission:website.manage');

        Route::post('/footer-links', [WebsiteController::class, 'storeFooterLink'])->name('footer-links.store')->middleware('permission:website.manage');
        Route::put('/footer-links/{link}', [WebsiteController::class, 'updateFooterLink'])->name('footer-links.update')->middleware('permission:website.manage');
        Route::delete('/footer-links/{link}', [WebsiteController::class, 'destroyFooterLink'])->name('footer-links.destroy')->middleware('permission:website.manage');

        Route::get('/pages/create', [WebsitePageController::class, 'create'])->name('pages.create')->middleware('permission:website.manage');
        Route::post('/pages', [WebsitePageController::class, 'store'])->name('pages.store')->middleware('permission:website.manage');
        Route::get('/pages/{page}/edit', [WebsitePageController::class, 'edit'])->name('pages.edit')->middleware('permission:website.manage');
        Route::put('/pages/{page}', [WebsitePageController::class, 'update'])->name('pages.update')->middleware('permission:website.manage');
        Route::put('/pages/{page}/sections', [WebsitePageController::class, 'updateSections'])->name('pages.sections')->middleware('permission:website.manage');
        Route::delete('/pages/{page}', [WebsitePageController::class, 'destroy'])->name('pages.destroy')->middleware('permission:website.manage');
    });
    Route::get('communications', [ConsolePageController::class, 'communications'])->name('communications.index')->middleware('permission:communications.view');
    Route::get('support', [ConsolePageController::class, 'support'])->name('support.index')->middleware('permission:support.view');
    Route::get('operations/health', [ConsolePageController::class, 'operations'])->name('operations.health')->middleware('permission:operations.view');
    Route::get('system/audit-logs', [ConsolePageController::class, 'auditLogs'])->name('system.audit-logs.index')->middleware('permission:audit_logs.view');
    Route::get('system/login-attempts', [ConsolePageController::class, 'loginAttempts'])->name('system.login-attempts.index')->middleware('permission:login_attempts.view');
    Route::get('system/administrators', [ConsolePageController::class, 'administrators'])->name('system.administrators.index')->middleware('permission:administrators.view');
    Route::get('system/roles', [ConsolePageController::class, 'roles'])->name('system.roles.index')->middleware('permission:roles.manage');
    Route::get('system/settings', [SettingsController::class, 'edit'])->name('system.settings.index')->middleware('permission:settings.view');
    Route::put('system/settings/{group}', [SettingsController::class, 'update'])->name('system.settings.update')->middleware('permission:settings.update')->whereIn('group', ['general', 'security']);
    Route::get('system/security', [ConsolePageController::class, 'security'])->name('system.security.index')->middleware('permission:security.manage');

    Route::resource('plans', PlanController::class)
        ->middlewareFor(['index', 'show'], 'permission:plans.view')
        ->middlewareFor(['create', 'store'], 'permission:plans.create')
        ->middlewareFor(['edit', 'update'], 'permission:plans.update')
        ->middlewareFor('destroy', 'permission:plans.archive');

    Route::resource('features', FeatureController::class)
        ->middlewareFor(['index', 'show'], 'permission:features.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:features.manage');

    Route::resource('subscriptions', SubscriptionController::class)->only(['index', 'show', 'update'])
        ->middlewareFor(['index', 'show'], 'permission:subscriptions.view')
        ->middlewareFor('update', 'permission:subscriptions.update');

    Route::post('tenants/{tenant}/retry', [TenantController::class, 'retry'])->name('tenants.retry')->middleware('permission:tenants.update');
    Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend')->middleware('permission:tenants.suspend');
    Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('tenants.activate')->middleware('permission:tenants.suspend');
    Route::post('tenants/{tenant}/migrate', [TenantController::class, 'migrate'])->name('tenants.migrate')->middleware('permission:tenants.update');
    Route::post('tenants/{tenant}/seed', [TenantController::class, 'seed'])->name('tenants.seed')->middleware('permission:tenants.update');

    Route::resource('tenants', TenantController::class)
        ->middlewareFor(['index', 'show'], 'permission:tenants.view')
        ->middlewareFor(['create', 'store'], 'permission:tenants.create')
        ->middlewareFor(['edit', 'update'], 'permission:tenants.update')
        ->middlewareFor('destroy', 'permission:tenants.delete');
});
