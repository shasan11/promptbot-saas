<?php

use App\Http\Controllers\Admin\InvitationAcceptanceController;
use App\Http\Controllers\Admin\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:central')->group(function (): void {
    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.accept');
    Route::post('invitations/{token}', [InvitationAcceptanceController::class, 'store'])->name('invitations.accept.store');
});

Route::middleware(['auth:central', 'central.active'])->group(function (): void {
    Route::get('two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->name('two-factor.challenge.verify');
});
