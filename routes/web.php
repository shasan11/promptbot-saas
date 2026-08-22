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
        // Named limiter: an unauthenticated request's throttle key is
        // sha1(domain|ip) with no route in it, so a bare `throttle:120,1`
        // shares one bucket with every other guest route on this domain.
        ->middleware('throttle:120,1,billing-webhook')->name('billing.webhooks.receive');

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

    // Catch-all for published CMS pages.
    //
    // Bound to the central domains explicitly. Tenant routes are registered in
    // `booted()` — after this file — so an unconstrained catch-all matches
    // first on a tenant domain and swallows every single-segment tenant URL
    // that is not in the exclusion list below. That is how /bot-profiles and
    // /ai came to 404 on a tenant while /channels worked: the list was simply
    // missing them, and nothing made it fail loudly when it fell behind.
    //
    // The domain binding is the actual fix — the CMS site only exists on the
    // central domains, so it should never have been reachable from a tenant
    // host in the first place. The exclusion list is kept as a second line of
    // defence for central routes, where order alone already protects them.
    $centralDomains = array_filter((array) config('tenancy.central_domains'));

    $registerCmsCatchAll = function (?string $domain) {
        $route = Route::get('/{slug}', [PublicWebsiteController::class, 'show'])
            ->where('slug', '^(?!(admin|administration|automation|bot-profiles|channels|connections|csat|customers|dashboard|developer|experience|forms|help|inbox|install|invitation|knowledge|login|logout|notifications|operations|portal|profile|quality|reports|search|settings|superadmin|tasks|templates|tenant|tickets|users|webhooks|workforce)$)[A-Za-z0-9_-]+$');

        return $domain === null ? $route : $route->domain($domain);
    };

    if ($centralDomains === []) {
        // A tenancy config with no central domains is a broken install, but
        // silently dropping the public website would be a worse failure than
        // the shadowing this guards against.
        $registerCmsCatchAll(null);
    } else {
        foreach ($centralDomains as $domain) {
            $registerCmsCatchAll($domain);
        }
    }
});
