<?php

use App\Http\Controllers\Installer\TenancyInstallerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicWebsiteController;
use App\Models\Domain;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

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

Route::get('/', function (Request $request, PublicWebsiteController $controller) {
    $host = strtolower($request->getHost());
    if (in_array($host, config('tenancy.central_domains', []), true)) return $controller->show($request);
    abort_unless(Domain::where('domain', $host)->exists(), 404);
    return redirect('/dashboard');
})->name('central.home');

Route::middleware('central.domain')->group(function (): void {

    Route::get('/central-dashboard', fn () => redirect()->route('superadmin.dashboard'))
        ->middleware(['auth:central', 'central.active', 'verified'])
        ->name('dashboard');

    Route::middleware(['auth:central', 'central.active'])->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    require __DIR__.'/auth.php';

    // Catch-all for published CMS pages. Registered last so it never shadows
    // an explicit named route above (login, profile, verify-email, etc.).
    Route::get('/{slug}', [PublicWebsiteController::class, 'show'])
        ->where('slug', '^(?!(admin|administration|automation|channels|connections|csat|customers|dashboard|developer|experience|forms|help|inbox|install|invitation|knowledge|login|logout|notifications|operations|portal|profile|quality|reports|search|settings|superadmin|tasks|templates|tenant|tickets|users|webhooks|workforce)$)[A-Za-z0-9_-]+$');
});
