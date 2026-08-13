<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PersonalTwoFactorController extends Controller
{
    public function show(Request $request, TotpService $totp): Response
    {
        $pending = $request->session()->get('central.two_factor_pending');
        $secret = $pending ? Crypt::decryptString($pending) : null;
        return Inertia::render('Admin/Security/TwoFactor', [
            'enabled' => $request->user('central')->two_factor_enabled,
            'required' => $request->user('central')->two_factor_required,
            'setup' => $secret ? ['secret' => $secret, 'uri' => $totp->uri($request->user('central'), $secret)] : null,
            'recoveryCodes' => session('central.two_factor_recovery_plain'),
        ]);
    }

    public function begin(Request $request, TotpService $totp): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:central']]);
        abort_if($request->user('central')->two_factor_enabled, 422, 'Two-factor authentication is already enabled.');
        $request->session()->put('central.two_factor_pending', Crypt::encryptString($totp->generateSecret()));
        return back()->with('status', 'Authenticator setup started.');
    }

    public function confirm(Request $request, TotpService $totp, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $pending = $request->session()->get('central.two_factor_pending');
        abort_unless($pending, 422, 'Start authenticator setup first.');
        $secret = Crypt::decryptString($pending);
        if (! $totp->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        }
        $plain = $totp->recoveryCodes();
        $user = $request->user('central');
        $user->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => $secret, 'two_factor_recovery_codes' => collect($plain)->map(fn ($code) => $totp->recoveryHash($code))->all()])->save();
        $request->session()->forget('central.two_factor_pending');
        $request->session()->put('central.two_factor_confirmed', true);
        $audit->record('platform_admin.two_factor_enabled', $user);
        return back()->with('status', 'Two-factor authentication enabled.')->with('central.two_factor_recovery_plain', $plain);
    }

    public function disable(Request $request, AuditLogService $audit): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:central']]);
        abort_if($request->user('central')->two_factor_required, 422, 'An administrator requires two-factor authentication for this account.');
        $user = $request->user('central');
        $user->forceFill(['two_factor_enabled' => false, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null])->save();
        $request->session()->forget(['central.two_factor_pending', 'central.two_factor_confirmed']);
        $audit->record('platform_admin.two_factor_disabled', $user, ['severity' => 'warning']);
        return back()->with('status', 'Two-factor authentication disabled.');
    }
}
