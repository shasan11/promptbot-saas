<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\Administration\DepartmentController;
use App\Http\Controllers\Tenant\Admin\Administration\InvitationController;
use App\Http\Controllers\Tenant\Admin\Administration\OverviewController;
use App\Http\Controllers\Tenant\Admin\Administration\RoleController;
use App\Http\Controllers\Tenant\Admin\Administration\TeamController;
use App\Http\Controllers\Tenant\Admin\Administration\UserController as AdministrationUserController;
use App\Http\Controllers\Tenant\Admin\DashboardController as TenantAdminDashboardController;
use App\Http\Controllers\Tenant\Admin\SettingController as TenantAdminSettingController;
use App\Http\Controllers\Tenant\Auth\TenantAuthenticatedSessionController;
use App\Http\Controllers\Tenant\InvitationAcceptController;
use Illuminate\Support\Facades\Route;
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

        Route::get('/invitation/{token}', [InvitationAcceptController::class, 'show'])->name('tenant.invitation.show');
        Route::post('/invitation/{token}/accept', [InvitationAcceptController::class, 'accept'])->name('tenant.invitation.accept');
    });

    Route::post('/logout', [TenantAuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:tenant')
        ->name('tenant.logout');

    Route::get('/', function () {
        return redirect()->route('tenant.admin.dashboard');
    })->middleware('auth:tenant')->name('tenant.dashboard');

    Route::middleware('auth:tenant')->name('tenant.admin.')->group(function (): void {
        Route::get('/dashboard', TenantAdminDashboardController::class)->name('dashboard');
        Route::get('/settings', [TenantAdminSettingController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [TenantAdminSettingController::class, 'update'])->name('settings.update');

        // Legacy route kept for any external bookmarks/links; the module now lives under /administration/users.
        Route::redirect('/users', '/administration/users')->name('users.index');

        Route::prefix('administration')->name('administration.')->group(function (): void {
            Route::get('/', OverviewController::class)->name('index');

            Route::post('users/bulk-action', [AdministrationUserController::class, 'bulkAction'])->name('users.bulk-action');
            Route::post('users/{user}/activate', [AdministrationUserController::class, 'activate'])->name('users.activate');
            Route::post('users/{user}/suspend', [AdministrationUserController::class, 'suspend'])->name('users.suspend');
            Route::post('users/{user}/deactivate', [AdministrationUserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('users/{user}/assign-roles', [AdministrationUserController::class, 'assignRoles'])->name('users.assign-roles');
            Route::resource('users', AdministrationUserController::class)->except(['destroy']);
            Route::delete('users/{user}', [AdministrationUserController::class, 'destroy'])->name('users.destroy');

            Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
            Route::post('invitations/{invitation}/revoke', [InvitationController::class, 'revoke'])->name('invitations.revoke');
            Route::resource('invitations', InvitationController::class)->only(['index', 'create', 'store', 'destroy']);

            Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.store');
            Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.destroy');
            Route::post('teams/{team}/set-lead', [TeamController::class, 'setLead'])->name('teams.set-lead');
            Route::post('teams/{team}/archive', [TeamController::class, 'archive'])->name('teams.archive');
            Route::post('teams/{team}/restore', [TeamController::class, 'restore'])->name('teams.restore');
            Route::resource('teams', TeamController::class)->except(['destroy']);
            Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

            Route::post('departments/{department}/archive', [DepartmentController::class, 'archive'])->name('departments.archive');
            Route::post('departments/{department}/restore', [DepartmentController::class, 'restore'])->name('departments.restore');
            Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);
            Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

            Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });
    });

    Route::redirect('/tenant/admin', '/dashboard');
    Route::redirect('/tenant/admin/dashboard', '/dashboard');
    Route::get('/tenant/admin/{path}', fn (string $path) => redirect('/'.$path))
        ->where('path', '.*');

    Route::redirect('/tenant/dashboard', '/dashboard')
        ->middleware('auth:tenant')
        ->name('tenant.legacy-dashboard');
});
