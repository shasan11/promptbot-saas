<?php

use App\Http\Controllers\Admin\AdministrationResourceController;
use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Admin\AdministratorInvitationController;
use App\Http\Controllers\Admin\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('administrators', [AdministratorController::class, 'index'])->middleware('central.permission:administrators.view')->name('administrators.index');
Route::get('administrators/invitations', [AdministratorInvitationController::class, 'index'])->middleware('central.permission:administrators.invite')->name('administrators.invitations.index');
Route::get('administrators/invitations/create', [AdministratorInvitationController::class, 'create'])->middleware('central.permission:administrators.invite')->name('administrators.invitations.create');
Route::post('administrators/invitations', [AdministratorInvitationController::class, 'store'])->middleware('central.permission:administrators.invite')->name('administrators.invitations.store');
Route::delete('administrators/invitations/{invitation}', [AdministratorInvitationController::class, 'revoke'])->middleware('central.permission:administrators.invite')->name('administrators.invitations.revoke');
Route::get('administrators/{administrator}', [AdministratorController::class, 'show'])->middleware('central.permission:administrators.view')->name('administrators.show');
Route::get('roles', [AdministrationResourceController::class, 'roles'])->middleware('central.permission:roles.view')->name('roles.index');
Route::get('permissions', [AdministrationResourceController::class, 'permissions'])->middleware('central.permission:permissions.view')->name('permissions.index');
Route::get('audit-logs', [AdministrationResourceController::class, 'auditLogs'])->middleware('central.permission:audit_logs.view')->name('audit-logs.index');
Route::get('login-attempts', [AdministrationResourceController::class, 'loginAttempts'])->middleware('central.permission:security.manage')->name('login-attempts.index');
Route::get('sessions', [AdministrationResourceController::class, 'sessions'])->middleware('central.permission:security.manage')->name('sessions.index');
Route::get('security/two-factor', [TwoFactorController::class, 'edit'])->middleware('central.permission:security.manage')->name('security.two-factor');
Route::post('security/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('central.permission:security.manage')->name('security.two-factor.confirm');
Route::delete('security/two-factor', [TwoFactorController::class, 'destroy'])->middleware('central.permission:security.manage')->name('security.two-factor.destroy');
Route::post('security/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->middleware('central.permission:security.manage')->name('security.recovery-codes.regenerate');
