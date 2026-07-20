<?php

use App\Http\Controllers\Installer\TenancyInstallerController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix('install/tenancy')->name('install.tenancy.')->group(function (): void {
    Route::get('status', [TenancyInstallerController::class, 'status'])->name('status');
    Route::post('license', [TenancyInstallerController::class, 'license'])->name('license');
    Route::post('tenant-provisioning', [TenancyInstallerController::class, 'tenantProvisioning'])->name('tenant-provisioning');
});

$centralRoutes = function (): void {
    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    })->name('central.home');

    Route::get('/dashboard', fn () => redirect()->route('superadmin.dashboard'))
        ->middleware(['auth:central', 'verified'])
        ->name('dashboard');

    Route::middleware('auth:central')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    require __DIR__.'/auth.php';
};

foreach (config('tenancy.central_domains', []) as $domain) {
    Route::domain($domain)->group($centralRoutes);
}
