<?php

use App\Http\Controllers\Portal\AccountSwitchController;
use App\Http\Controllers\Portal\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Portal\Auth\EmailVerificationController;
use App\Http\Controllers\Portal\Auth\GoogleAuthenticationController;
use App\Http\Controllers\Portal\Auth\NewPasswordController;
use App\Http\Controllers\Portal\Auth\PasswordResetLinkController;
use App\Http\Controllers\Portal\Auth\RegisteredUserController;
use App\Http\Controllers\Portal\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Portal\BillingController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\InvitationController;
use App\Http\Controllers\Portal\MemberController;
use App\Http\Controllers\Portal\NotificationController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\SupportController;
use App\Http\Controllers\Portal\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('account')->name('portal.')->middleware('portal.enabled')->group(function (): void {
    Route::middleware('guest:portal')->group(function (): void {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
        Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');
        Route::get('oauth/google', [GoogleAuthenticationController::class, 'redirect'])->middleware('throttle:20,1')->name('oauth.google.redirect');
        Route::get('oauth/google/callback', [GoogleAuthenticationController::class, 'callback'])->middleware('throttle:20,1')->name('oauth.google.callback');
        Route::get('oauth/google/onboarding', [GoogleAuthenticationController::class, 'onboarding'])->name('oauth.google.onboarding');
        Route::post('oauth/google/onboarding', [GoogleAuthenticationController::class, 'complete'])->middleware('throttle:10,1')->name('oauth.google.complete');
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
        Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:6,1')->name('two-factor.store');
    });

    Route::get('invitations/{invitation}/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
    Route::post('invitations/{invitation}/{token}', [InvitationController::class, 'store'])->name('invitations.store');

    Route::middleware(['auth:portal', 'portal.active', 'portal.session'])->group(function (): void {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
        Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
    });

    Route::middleware(['auth:portal', 'portal.active', 'portal.session', 'portal.verified', 'portal.account'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('switch-account/{account}', AccountSwitchController::class)->name('accounts.switch');

        Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
        Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
        Route::post('workspaces/{workspace}/retry', [WorkspaceController::class, 'retry'])->middleware('throttle:3,10')->name('workspaces.retry');

        Route::get('billing', [BillingController::class, 'overview'])->name('billing.overview');
        Route::get('billing/subscriptions', [BillingController::class, 'subscriptions'])->name('billing.subscriptions');
        Route::put('billing/subscriptions/{subscription}', [BillingController::class, 'changeSubscription'])->name('billing.subscriptions.change');
        Route::post('billing/subscriptions/{subscription}/cancel', [BillingController::class, 'cancelSubscription'])->name('billing.subscriptions.cancel');
        Route::post('billing/subscriptions/{subscription}/resume', [BillingController::class, 'resumeSubscription'])->name('billing.subscriptions.resume');
        Route::post('billing/subscriptions/{subscription}/coupon', [BillingController::class, 'applyCoupon'])->name('billing.subscriptions.coupon');
        Route::delete('billing/subscriptions/{subscription}/coupon', [BillingController::class, 'removeCoupon'])->name('billing.subscriptions.coupon.remove');
        Route::get('billing/invoices', [BillingController::class, 'invoices'])->name('billing.invoices');
        Route::get('billing/invoices/{invoice}', [BillingController::class, 'invoice'])->name('billing.invoices.show');
        Route::get('billing/invoices/{invoice}/download', [BillingController::class, 'downloadInvoice'])->name('billing.invoices.download');
        Route::post('billing/invoices/{invoice}/pay', [BillingController::class, 'payInvoice'])->name('billing.invoices.pay');
        Route::get('billing/payments', [BillingController::class, 'payments'])->name('billing.payments');
        Route::post('billing/payments/{payment}/retry', [BillingController::class, 'retryPayment'])->name('billing.payments.retry');
        Route::get('billing/payment-methods', [BillingController::class, 'paymentMethods'])->name('billing.payment-methods');
        Route::post('billing/payment-methods', [BillingController::class, 'storePaymentMethod'])->name('billing.payment-methods.store');
        Route::put('billing/payment-methods/{method}/default', [BillingController::class, 'defaultPaymentMethod'])->name('billing.payment-methods.default');
        Route::delete('billing/payment-methods/{method}', [BillingController::class, 'destroyPaymentMethod'])->name('billing.payment-methods.destroy');
        Route::get('billing/profile', [BillingController::class, 'profile'])->name('billing.profile');
        Route::put('billing/profile', [BillingController::class, 'updateProfile'])->name('billing.profile.update');

        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
        Route::post('members/transfer-ownership', [MemberController::class, 'transfer'])->name('members.transfer');

        Route::get('support', [SupportController::class, 'index'])->name('support.index');
        Route::get('support/create', [SupportController::class, 'create'])->name('support.create');
        Route::post('support', [SupportController::class, 'store'])->name('support.store');
        Route::get('support/{ticket}', [SupportController::class, 'show'])->name('support.show');
        Route::post('support/{ticket}/reply', [SupportController::class, 'reply'])->name('support.reply');
        Route::get('support/{ticket}/messages/{message}/attachment', [SupportController::class, 'downloadAttachment'])->name('support.attachments.download');
        Route::post('support/{ticket}/close', [SupportController::class, 'close'])->name('support.close');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('security', [ProfileController::class, 'security'])->name('security');
        Route::put('security/password', [ProfileController::class, 'password'])->name('security.password');
        Route::post('security/two-factor', [ProfileController::class, 'beginTwoFactor'])->name('security.two-factor.begin');
        Route::post('security/two-factor/confirm', [ProfileController::class, 'confirmTwoFactor'])->middleware('throttle:6,1')->name('security.two-factor.confirm');
        Route::delete('security/two-factor', [ProfileController::class, 'disableTwoFactor'])->name('security.two-factor.disable');
        Route::delete('security/sessions/{session}', [ProfileController::class, 'revokeSession'])->name('security.sessions.destroy');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::put('notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    });
});
