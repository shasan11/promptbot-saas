<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/admin', '/superadmin');
Route::redirect('/admin/dashboard', '/superadmin/dashboard');
Route::get('/admin/{path}', fn (string $path) => redirect('/superadmin/'.$path))->where('path', '.*');

Route::middleware(['central.domain'])->prefix('superadmin')->name('superadmin.')->group(function (): void {
    require __DIR__.'/superadmin/auth.php';

    Route::middleware(['auth:central', 'central.active', 'central.2fa'])->group(function (): void {
        Route::redirect('/', '/superadmin/dashboard');

        require __DIR__.'/superadmin/dashboard.php';
        require __DIR__.'/superadmin/tenants.php';
        require __DIR__.'/superadmin/billing.php';
        require __DIR__.'/superadmin/platform.php';
        require __DIR__.'/superadmin/website.php';
        require __DIR__.'/superadmin/communications.php';
        require __DIR__.'/superadmin/support.php';
        require __DIR__.'/superadmin/operations.php';
        require __DIR__.'/superadmin/administration.php';
        require __DIR__.'/superadmin/settings.php';
    });
});
