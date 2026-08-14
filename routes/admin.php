<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CustomerAccountController;
use App\Http\Controllers\Admin\PortalUserController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\WebsiteController;
use App\Http\Controllers\Admin\WebsitePageController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\PersonalTwoFactorController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\GrowthController;
use App\Http\Controllers\Admin\ProvisioningMonitorController;
use App\Http\Controllers\LegacyPathRedirectController;
use Illuminate\Support\Facades\Route;

Route::redirect('/admin', '/superadmin');
Route::redirect('/admin/dashboard', '/superadmin/dashboard');
Route::get('/admin/{path}', [LegacyPathRedirectController::class, 'superadmin'])
    ->where('path', '.*');

Route::middleware(['central.domain', 'auth:central', 'central.active', 'central.password'])->prefix('superadmin')->name('superadmin.')->group(function (): void {
    Route::get('security/two-factor/setup', [PersonalTwoFactorController::class, 'show'])->name('security.two-factor.setup');
    Route::post('security/two-factor/setup', [PersonalTwoFactorController::class, 'begin'])->name('security.two-factor.begin');
    Route::post('security/two-factor/confirm', [PersonalTwoFactorController::class, 'confirm'])->middleware('throttle:6,1')->name('security.two-factor.confirm');
    Route::delete('security/two-factor', [PersonalTwoFactorController::class, 'disable'])->name('security.two-factor.disable');
});

Route::middleware(['central.domain', 'auth:central', 'central.active', 'central.password', 'central.2fa'])->prefix('superadmin')->name('superadmin.')->group(function (): void {
    Route::redirect('/', '/superadmin/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard')->middleware('permission:dashboard.view');
    Route::get('/search', SearchController::class)->name('search')->middleware('permission:dashboard.view');

    Route::prefix('customers')->name('customers.')->group(function (): void {
        Route::get('accounts', [CustomerAccountController::class, 'index'])->name('accounts.index')->middleware('permission:customers.view');
        Route::get('accounts/create', [CustomerAccountController::class, 'create'])->name('accounts.create')->middleware('permission:customers.manage');
        Route::post('accounts', [CustomerAccountController::class, 'store'])->name('accounts.store')->middleware('permission:customers.manage');
        Route::get('accounts/{account}', [CustomerAccountController::class, 'show'])->name('accounts.show')->middleware('permission:customers.view');
        Route::get('accounts/{account}/edit', [CustomerAccountController::class, 'edit'])->name('accounts.edit')->middleware('permission:customers.manage');
        Route::put('accounts/{account}', [CustomerAccountController::class, 'update'])->name('accounts.update')->middleware('permission:customers.manage');
        Route::put('accounts/{account}/status', [CustomerAccountController::class, 'status'])->name('accounts.status')->middleware('permission:customers.manage');
        Route::post('accounts/{account}/notes', [CustomerAccountController::class, 'note'])->name('accounts.notes.store')->middleware('permission:customers.manage');
        Route::post('accounts/{account}/limits', [CustomerAccountController::class, 'storeLimit'])->name('accounts.limits.store')->middleware('permission:customers.manage');
        Route::delete('accounts/{account}/limits/{limit}', [CustomerAccountController::class, 'destroyLimit'])->name('accounts.limits.destroy')->middleware('permission:customers.manage');
        Route::get('users', [PortalUserController::class, 'index'])->name('users.index')->middleware('permission:customers.view');
        Route::post('users', [PortalUserController::class, 'store'])->name('users.store')->middleware('permission:customers.manage');
    });

    Route::get('revenue', RevenueController::class)->name('revenue.index')->middleware('permission:revenue.view');
    Route::get('growth', GrowthController::class)->name('growth.index')->middleware('permission:revenue.view');
    Route::get('usage', UsageController::class)->name('usage.index')->middleware('permission:usage.view');
    Route::resource('coupons', CouponController::class)->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:coupons.view')
        ->middlewareFor(['store', 'update', 'destroy'], 'permission:coupons.manage');

    Route::get('communications/email-templates', [EmailTemplateController::class, 'index'])
        ->name('communications.email-templates.index')->middleware('permission:communications.view');
    Route::put('communications/email-templates/{template}', [EmailTemplateController::class, 'update'])
        ->name('communications.email-templates.update')->middleware('permission:communications.manage');
    Route::post('communications/email-templates/{template}/test', [EmailTemplateController::class, 'test'])
        ->name('communications.email-templates.test')->middleware('permission:communications.manage');
    Route::post('communications/bulk-email', [EmailTemplateController::class, 'bulk'])
        ->name('communications.bulk-email.store')->middleware('permission:communications.manage');

    Route::prefix('security')->name('security.')->group(function (): void {
        Route::get('admins', [SecurityController::class, 'admins'])->name('admins.index')->middleware('permission:administrators.view');
        Route::post('admins', [SecurityController::class, 'storeAdmin'])->name('admins.store')->middleware('permission:administrators.manage');
        Route::put('admins/{admin}/status', [SecurityController::class, 'status'])->name('admins.status')->middleware('permission:administrators.manage');
        Route::post('admins/{admin}/access', [SecurityController::class, 'access'])->name('admins.access')->middleware('permission:administrators.manage');
        Route::get('roles', [SecurityController::class, 'roles'])->name('roles.index')->middleware('permission:roles.manage');
        Route::put('roles/{role}', [SecurityController::class, 'updateRole'])->name('roles.update')->middleware('permission:roles.manage');
        Route::get('audit-logs', [SecurityController::class, 'audit'])->name('audit.index')->middleware('permission:audit_logs.view');
        Route::get('login-activity', [SecurityController::class, 'logins'])->name('logins.index')->middleware('permission:login_attempts.view');
    });

    Route::redirect('billing/plans', '/superadmin/plans')->name('billing.plans.index');
    Route::redirect('billing/subscriptions', '/superadmin/subscriptions')->name('billing.subscriptions.index');

    Route::prefix('billing/payments')->name('billing.payments.')->group(function (): void {
        Route::get('/', [PaymentController::class, 'index'])->name('index')->middleware('permission:payments.view');
        Route::get('/create', [PaymentController::class, 'create'])->name('create')->middleware('permission:payments.manage');
        Route::post('/', [PaymentController::class, 'store'])->name('store')->middleware('permission:payments.manage');
        Route::get('/{payment}/edit', [PaymentController::class, 'edit'])->name('edit')->middleware('permission:payments.manage');
        Route::put('/{payment}', [PaymentController::class, 'update'])->name('update')->middleware('permission:payments.manage');
        Route::post('/{payment}/refund', [PaymentController::class, 'refund'])->name('refund')->middleware('permission:payments.manage');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show')->middleware('permission:payments.view');
    });
    Route::get('billing/refunds', RefundController::class)->name('billing.refunds.index')->middleware('permission:payments.view');
    Route::redirect('refunds', '/superadmin/billing/refunds');
    Route::redirect('payments', '/superadmin/billing/payments');
    Route::redirect('invoices', '/superadmin/billing/invoices');

    Route::prefix('billing/invoices')->name('billing.invoices.')->group(function (): void {
        Route::get('/', [InvoiceController::class, 'index'])->name('index')->middleware('permission:invoices.view');
        Route::get('/create', [InvoiceController::class, 'create'])->name('create')->middleware('permission:invoices.manage');
        Route::post('/', [InvoiceController::class, 'store'])->name('store')->middleware('permission:invoices.manage');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show')->middleware('permission:invoices.view');
        Route::post('/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('mark-paid')->middleware('permission:invoices.manage');
        Route::post('/{invoice}/void', [InvoiceController::class, 'void'])->name('void')->middleware('permission:invoices.manage');
    });

    Route::prefix('tickets')->name('tickets.')->group(function (): void {
        Route::get('/', [SupportTicketController::class, 'index'])->name('index')->middleware('permission:support.view');
        Route::get('/create', [SupportTicketController::class, 'create'])->name('create')->middleware('permission:support.manage');
        Route::post('/', [SupportTicketController::class, 'store'])->name('store')->middleware('permission:support.manage');
        Route::put('/{ticket}', [SupportTicketController::class, 'update'])->name('update')->middleware('permission:support.manage');
        Route::post('/{ticket}/messages', [SupportTicketController::class, 'addMessage'])->name('messages.store')->middleware('permission:support.manage');
        Route::get('/{ticket}/messages/{message}/attachment', [SupportTicketController::class, 'downloadAttachment'])->name('attachments.download')->middleware('permission:support.view');
        Route::get('/{ticket}', [SupportTicketController::class, 'show'])->name('show')->middleware('permission:support.view');
    });
    Route::redirect('support', '/superadmin/tickets');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:dashboard.view');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export')->middleware('permission:dashboard.view');

    Route::prefix('operations')->name('operations.')->group(function (): void {
        Route::get('/provisioning', ProvisioningMonitorController::class)->name('provisioning')->middleware('permission:operations.view');
        Route::get('/health', [SystemHealthController::class, 'index'])->name('health')->middleware('permission:operations.view');
        Route::post('/cache/clear', [SystemHealthController::class, 'clearCaches'])->name('cache.clear')->middleware('permission:maintenance.manage');
        Route::post('/failed-jobs/retry-all', [SystemHealthController::class, 'retryAll'])->name('failed.retry-all')->middleware('permission:maintenance.manage');
        Route::post('/failed-jobs/flush', [SystemHealthController::class, 'flushFailed'])->name('failed.flush')->middleware('permission:maintenance.manage');
        Route::post('/failed-jobs/{failedJob}/retry', [SystemHealthController::class, 'retryFailed'])->name('failed.retry')->middleware('permission:maintenance.manage');
        Route::post('/failed-jobs/{failedJob}/forget', [SystemHealthController::class, 'forgetFailed'])->name('failed.forget')->middleware('permission:maintenance.manage');
    });

    Route::prefix('website')->name('website.')->group(function (): void {
        Route::get('/', [WebsiteController::class, 'index'])->name('index')->middleware('permission:website.view');
        Route::put('/settings', [WebsiteController::class, 'updateSettings'])->name('settings.update')->middleware('permission:website.manage');
        Route::post('/media', [WebsiteController::class, 'uploadMedia'])->name('media.store')->middleware('permission:website.manage');
        Route::post('/media/{media}', [WebsiteController::class, 'updateMedia'])->name('media.update')->middleware('permission:website.manage');
        Route::delete('/media/{media}', [WebsiteController::class, 'destroyMedia'])->name('media.destroy')->middleware('permission:website.manage');
        Route::post('/redirects', [WebsiteController::class, 'storeRedirect'])->name('redirects.store')->middleware('permission:website.manage');
        Route::delete('/redirects/{redirect}', [WebsiteController::class, 'destroyRedirect'])->name('redirects.destroy')->middleware('permission:website.manage');
        Route::post('/blog', [WebsiteController::class, 'storeBlogPost'])->name('blog.store')->middleware('permission:website.manage');
        Route::put('/blog/{post}', [WebsiteController::class, 'updateBlogPost'])->name('blog.update')->middleware('permission:website.manage');
        Route::delete('/blog/{post}', [WebsiteController::class, 'destroyBlogPost'])->name('blog.destroy')->middleware('permission:website.manage');
        Route::post('/forms', [WebsiteController::class, 'storeForm'])->name('forms.store')->middleware('permission:website.manage');
        Route::put('/forms/{form}', [WebsiteController::class, 'updateForm'])->name('forms.update')->middleware('permission:website.manage');
        Route::post('/categories', [WebsiteController::class, 'storeCategory'])->name('categories.store')->middleware('permission:website.manage');
        Route::delete('/categories/{category}', [WebsiteController::class, 'destroyCategory'])->name('categories.destroy')->middleware('permission:website.manage');
        Route::post('/tags', [WebsiteController::class, 'storeTag'])->name('tags.store')->middleware('permission:website.manage');
        Route::delete('/tags/{tag}', [WebsiteController::class, 'destroyTag'])->name('tags.destroy')->middleware('permission:website.manage');
        Route::put('/leads/{submission}', [WebsiteController::class, 'updateLead'])->name('leads.update')->middleware('permission:website.manage');
        Route::get('/leads-export', [WebsiteController::class, 'exportLeads'])->name('leads.export')->middleware('permission:website.manage');

        Route::post('/navigation', [WebsiteController::class, 'storeNavigationItem'])->name('navigation.store')->middleware('permission:website.manage');
        Route::put('/navigation-order', [WebsiteController::class, 'reorderNavigation'])->name('navigation.order')->middleware('permission:website.manage');
        Route::put('/navigation/{item}', [WebsiteController::class, 'updateNavigationItem'])->name('navigation.update')->middleware('permission:website.manage');
        Route::delete('/navigation/{item}', [WebsiteController::class, 'destroyNavigationItem'])->name('navigation.destroy')->middleware('permission:website.manage');

        Route::post('/footer-links', [WebsiteController::class, 'storeFooterLink'])->name('footer-links.store')->middleware('permission:website.manage');
        Route::put('/footer-links-order', [WebsiteController::class, 'reorderFooterLinks'])->name('footer-links.order')->middleware('permission:website.manage');
        Route::put('/footer-links/{link}', [WebsiteController::class, 'updateFooterLink'])->name('footer-links.update')->middleware('permission:website.manage');
        Route::delete('/footer-links/{link}', [WebsiteController::class, 'destroyFooterLink'])->name('footer-links.destroy')->middleware('permission:website.manage');

        Route::get('/pages/create', [WebsitePageController::class, 'create'])->name('pages.create')->middleware('permission:website.manage');
        Route::post('/pages', [WebsitePageController::class, 'store'])->name('pages.store')->middleware('permission:website.manage');
        Route::get('/pages/{page}/edit', [WebsitePageController::class, 'edit'])->name('pages.edit')->middleware('permission:website.manage');
        Route::put('/pages/{page}', [WebsitePageController::class, 'update'])->name('pages.update')->middleware('permission:website.manage');
        Route::put('/pages/{page}/sections', [WebsitePageController::class, 'updateSections'])->name('pages.sections')->middleware('permission:website.manage');
        Route::post('/pages/{page}/revisions/{revision}/restore', [WebsitePageController::class, 'restore'])->name('pages.revisions.restore')->middleware('permission:website.manage');
        Route::delete('/pages/{page}', [WebsitePageController::class, 'destroy'])->name('pages.destroy')->middleware('permission:website.manage');
    });

    Route::get('system/settings', [SettingsController::class, 'edit'])->name('system.settings.index')->middleware('permission:settings.view');
    Route::put('system/settings/{group}', [SettingsController::class, 'update'])
        ->name('system.settings.update')
        ->middleware('permission:settings.update')
        ->whereIn('group', ['general', 'security', 'email', 'mail', 'payment', 'branding', 'registration', 'customer_portal', 'billing', 'tax', 'localization', 'trials', 'tenant_provisioning', 'notifications', 'maintenance']);
    Route::post('system/settings/test-mail', [SettingsController::class, 'testMail'])
        ->name('system.settings.test-mail')
        ->middleware('permission:settings.update');

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

    Route::resource('services', TenantController::class)
        ->parameters(['services' => 'tenant'])
        ->middlewareFor(['index', 'show'], 'permission:tenants.view')
        ->middlewareFor(['create', 'store'], 'permission:tenants.create')
        ->middlewareFor(['edit', 'update'], 'permission:tenants.update')
        ->middlewareFor('destroy', 'permission:tenants.delete');
});
