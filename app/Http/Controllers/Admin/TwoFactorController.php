<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function edit(Request $request): Response
    {
        $user = $request->user('central');

        if (! $user->two_factor_secret) {
            $this->twoFactor->generateSecret($user);
            $user->refresh();
        }

        return Inertia::render('Admin/Security/TwoFactor', [
            'confirmed' => (bool) $user->two_factor_confirmed_at,
            'required' => (bool) $user->two_factor_required || $user->hasRole('Platform Owner'),
            'provisioningUri' => $this->twoFactor->provisioningUri($user),
            'recoveryCodesRemaining' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);

        if (! $this->twoFactor->confirm($request->user('central'), $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        }

        $request->session()->put('central.2fa.passed', true);
        app(AuditLogService::class)->record('administrator.two_factor_confirmed');

        return back()->with('status', 'Two-factor authentication enabled.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        abort_unless(Hash::check($validated['password'], $request->user('central')->password), 403);

        $this->twoFactor->disable($request->user('central'));
        $request->session()->forget('central.2fa.passed');
        app(AuditLogService::class)->record('administrator.two_factor_disabled', null, ['severity' => 'warning']);

        return back()->with('status', 'Two-factor authentication disabled.');
    }

    public function challenge(): Response
    {
        return Inertia::render('Admin/Security/TwoFactorChallenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $user = $request->user('central');
        $valid = filled($validated['code'] ?? null)
            ? $this->twoFactor->verifyChallenge($user, $validated['code'])
            : $this->twoFactor->consumeRecoveryCode($user, (string) ($validated['recovery_code'] ?? ''));

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'The two-factor challenge is invalid.']);
        }

        $request->session()->put('central.2fa.passed', true);

        return redirect()->intended(route('superadmin.dashboard', absolute: false));
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $codes = $this->twoFactor->regenerateRecoveryCodes($request->user('central'));
        app(AuditLogService::class)->record('administrator.recovery_codes_regenerated');

        return back()->with('status', 'Recovery codes regenerated. Store these now: '.implode(', ', $codes));
    }
}
