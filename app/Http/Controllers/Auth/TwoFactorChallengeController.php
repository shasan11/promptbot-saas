<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CentralUser;
use App\Models\PlatformAdminLoginAttempt;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('central.two_factor_user_id')) {
            return redirect()->route('login');
        }
        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = CentralUser::findOrFail($request->session()->get('central.two_factor_user_id'));
        $validTotp = $user->two_factor_secret && $totp->verify($user->two_factor_secret, $data['code']);
        $recovery = $user->two_factor_recovery_codes ?? [];
        $recoveryIndex = array_search($totp->recoveryHash($data['code']), $recovery, true);

        if (! $validTotp && $recoveryIndex === false) {
            PlatformAdminLoginAttempt::create(['administrator_id' => $user->id, 'email' => $user->email, 'successful' => false, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'failure_reason' => 'invalid_two_factor', 'attempted_at' => now()]);
            throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        }
        if ($recoveryIndex !== false) {
            unset($recovery[$recoveryIndex]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($recovery)])->save();
        }

        $remember = (bool) $request->session()->pull('central.two_factor_remember', false);
        $request->session()->forget('central.two_factor_user_id');
        Auth::guard('central')->login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('central.two_factor_confirmed', true);
        app(AuditLogService::class)->record('platform_admin.login', $user, ['new_values' => ['two_factor' => true, 'recovery_code' => $recoveryIndex !== false]]);
        return redirect()->intended(route('superadmin.dashboard', absolute: false));
    }
}
