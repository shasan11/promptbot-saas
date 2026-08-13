<?php

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use App\Models\PortalLoginActivity;
use App\Models\PortalUser;
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
        if (! $request->session()->has('portal.two_factor_user_id')) return redirect()->route('portal.login');
        return Inertia::render('Portal/Auth/TwoFactorChallenge');
    }

    public function store(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = PortalUser::findOrFail($request->session()->get('portal.two_factor_user_id'));
        $validTotp = $user->two_factor_secret && $totp->verify($user->two_factor_secret, $data['code']);
        $recovery = $user->two_factor_recovery_codes ?? [];
        $recoveryHash = $totp->recoveryHash($data['code']);
        $recoveryIndex = array_search($recoveryHash, $recovery, true);

        if (! $validTotp && $recoveryIndex === false) {
            PortalLoginActivity::create(['portal_user_id' => $user->id, 'email' => $user->email, 'event' => 'login.two_factor_failed', 'successful' => false, 'ip_address' => $request->ip(), 'user_agent' => str($request->userAgent())->limit(1000), 'created_at' => now()]);
            throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        }
        if ($recoveryIndex !== false) {
            unset($recovery[$recoveryIndex]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($recovery)])->save();
        }

        $remember = (bool) $request->session()->pull('portal.two_factor_remember', false);
        $request->session()->forget('portal.two_factor_user_id');
        Auth::guard('portal')->login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        PortalLoginActivity::create(['portal_user_id' => $user->id, 'email' => $user->email, 'event' => 'login.succeeded', 'successful' => true, 'ip_address' => $request->ip(), 'user_agent' => str($request->userAgent())->limit(1000), 'metadata' => ['two_factor' => true, 'recovery_code' => $recoveryIndex !== false], 'created_at' => now()]);

        return redirect()->intended(route('portal.dashboard', absolute: false));
    }
}
