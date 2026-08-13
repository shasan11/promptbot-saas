<?php

use App\Http\Controllers\Installer\TenancyInstallerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['central.domain', 'installer.open', 'throttle:30,1'])
    ->prefix('install/tenancy')->name('install.tenancy.')->group(function (): void {
        Route::get('status', [TenancyInstallerController::class, 'status'])->name('status');
        Route::post('license', [TenancyInstallerController::class, 'license'])->name('license');
        Route::post('tenant-provisioning', [TenancyInstallerController::class, 'tenantProvisioning'])->name('tenant-provisioning');
    });

Route::middleware(['central.domain', 'installer.open', 'throttle:20,1'])->group(function (): void {
    Route::get('/install', [TenancyInstallerController::class, 'index'])->name('install.index');
    Route::post('/install', [TenancyInstallerController::class, 'complete'])->name('install.complete');
});

Route::get('/', [PublicWebsiteController::class, 'home'])->name('central.home');

Route::middleware('central.domain')->group(function (): void {

    Route::post('/billing/webhooks/{provider}', PaymentWebhookController::class)
        ->middleware('throttle:120,1')->name('billing.webhooks.receive');

    Route::redirect('/central-dashboard', '/superadmin/dashboard')
        ->middleware(['auth:central', 'central.active', 'verified'])
        ->name('dashboard');

    Route::middleware(['auth:central', 'central.active'])->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    require __DIR__.'/auth.php';
    require __DIR__.'/portal.php';

    Route::get('/sitemap.xml', [PublicWebsiteController::class, 'sitemap'])->name('website.sitemap');
    Route::get('/robots.txt', [PublicWebsiteController::class, 'robots'])->name('website.robots');
    Route::get('/website-preview/{page}', [PublicWebsiteController::class, 'preview'])->middleware('signed')->name('website.preview');
    Route::get('/blog', [PublicWebsiteController::class, 'blog'])->name('website.blog');
    Route::get('/blog/{slug}', [PublicWebsiteController::class, 'post'])->name('website.blog.show');
    Route::post('/forms/{form}', [PublicWebsiteController::class, 'submitForm'])->middleware('throttle:10,1')->name('website.forms.submit');

    // Catch-all for published CMS pages. Registered last so it never shadows
    // an explicit named route above (login, profile, verify-email, etc.).
    Route::get('/{slug}', [PublicWebsiteController::class, 'show'])
        ->where('slug', '^(?!(admin|administration|automation|channels|connections|csat|customers|dashboard|developer|experience|forms|help|inbox|install|invitation|knowledge|login|logout|notifications|operations|portal|profile|quality|reports|search|settings|superadmin|tasks|templates|tenant|tickets|users|webhooks|workforce)$)[A-Za-z0-9_-]+$');
});
