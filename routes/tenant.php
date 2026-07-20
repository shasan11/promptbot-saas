<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Auth\TenantAuthenticatedSessionController;
use App\Http\Controllers\Tenant\Admin\DashboardController as TenantAdminDashboardController;
use App\Http\Controllers\Tenant\Admin\SettingController as TenantAdminSettingController;
use App\Http\Controllers\Tenant\Admin\UserController as TenantAdminUserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'tenant.active',
])->group(function () {
    Route::middleware('guest:tenant')->group(function (): void {
        Route::redirect('/tenant/login', '/login');
        Route::get('/login', [TenantAuthenticatedSessionController::class, 'create'])->name('tenant.login');
        Route::post('/login', [TenantAuthenticatedSessionController::class, 'store']);
    });

    Route::post('/logout', [TenantAuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:tenant')
        ->name('tenant.logout');

    Route::get('/', function () {
        return redirect()->route('tenant.admin.dashboard');
    })->middleware('auth:tenant')->name('tenant.dashboard');

    Route::middleware('auth:tenant')->name('tenant.admin.')->group(function (): void {
        Route::get('/dashboard', TenantAdminDashboardController::class)->name('dashboard');
        Route::get('/users', [TenantAdminUserController::class, 'index'])->name('users.index');
        Route::get('/settings', [TenantAdminSettingController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [TenantAdminSettingController::class, 'update'])->name('settings.update');
    });

    Route::redirect('/tenant/admin', '/dashboard');
    Route::redirect('/tenant/admin/dashboard', '/dashboard');
    Route::get('/tenant/admin/{path}', fn (string $path) => redirect('/'.$path))
        ->where('path', '.*');

    Route::get('/tenant/dashboard', function () {
        return Inertia::render('Tenant/Dashboard', [
            'tenant' => [
                'id' => tenant('id'),
                'company_name' => tenant('company_name'),
            ],
        ]);
    })->middleware('auth:tenant')->name('tenant.legacy-dashboard');
});
