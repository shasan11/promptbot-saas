<?php

use App\Http\Controllers\Installer\TenancyInstallerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicWebsiteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['central.domain', 'installer.open', 'throttle:30,1'])
    ->prefix('install/tenancy')->name('install.tenancy.')->group(function (): void {
        Route::get('status', [TenancyInstallerController::class, 'status'])->name('status');
        Route::post('license', [TenancyInstallerController::class, 'license'])->name('license');
        Route::post('tenant-provisioning', [TenancyInstallerController::class, 'tenantProvisioning'])->name('tenant-provisioning');
    });

$centralRoutes = function (): void {
    Route::get('/', [PublicWebsiteController::class, 'show'])->name('central.home');

    Route::get('/dashboard', fn () => redirect()->route('superadmin.dashboard'))
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
    Route::get('/{slug}', [PublicWebsiteController::class, 'show'])->where('slug', '[A-Za-z0-9_-]+');
};

foreach (config('tenancy.central_domains', []) as $domain) {
    Route::domain($domain)->group($centralRoutes);
}
