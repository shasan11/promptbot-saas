<?php

use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('tenants', [TenantController::class, 'index'])->middleware('central.permission:tenants.view')->name('tenants.index');
Route::get('tenants/create', [TenantController::class, 'create'])->middleware('central.permission:tenants.create')->name('tenants.create');
Route::post('tenants', [TenantController::class, 'store'])->middleware('central.permission:tenants.create')->name('tenants.store');
Route::get('tenants/{tenant}', [TenantController::class, 'show'])->middleware('central.permission:tenants.view')->name('tenants.show');
Route::get('tenants/{tenant}/edit', [TenantController::class, 'edit'])->middleware('central.permission:tenants.update')->name('tenants.edit');
Route::match(['put', 'patch'], 'tenants/{tenant}', [TenantController::class, 'update'])->middleware('central.permission:tenants.update')->name('tenants.update');
Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->middleware('central.permission:tenants.delete')->name('tenants.destroy');
Route::post('tenants/{tenant}/retry', [TenantController::class, 'retry'])->middleware('central.permission:tenants.create')->name('tenants.retry');
Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->middleware('central.permission:tenants.suspend')->name('tenants.suspend');
Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate'])->middleware('central.permission:tenants.activate')->name('tenants.activate');
Route::post('tenants/{tenant}/migrate', [TenantController::class, 'migrate'])->middleware('central.permission:tenants.migrate')->name('tenants.migrate');
Route::post('tenants/{tenant}/seed', [TenantController::class, 'seed'])->middleware('central.permission:tenants.seed')->name('tenants.seed');
