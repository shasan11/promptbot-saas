<?php

namespace App\Http\Controllers\Portal\Auth;

use App\Enums\PortalUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\PortalLoginActivity;
use App\Models\PortalUserSession;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'loginRoute' => 'portal.login.store',
            'passwordRequestRoute' => 'portal.password.request',
            'panelName' => 'Customer Portal',
            'status' => session('status'),
        ]);
    }

    public function store(PortalLoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $user = $request->user('portal');
        if ($user->status !== PortalUserStatus::Active) {
            PortalLoginActivity::create(['portal_user_id' => $user->id, 'email' => $user->email, 'event' => 'login.failed', 'successful' => false, 'ip_address' => $request->ip(), 'user_agent' => str($request->userAgent())->limit(1000), 'metadata' => ['reason' => 'inactive'], 'created_at' => now()]);
            Auth::guard('portal')->logout();
            return back()->withErrors(['email' => 'This portal account is not active.']);
        }

        $request->session()->regenerate();

        if ($user->two_factor_enabled) {
            $request->session()->put('portal.two_factor_user_id', $user->getKey());
            $request->session()->put('portal.two_factor_remember', $request->boolean('remember'));
            PortalLoginActivity::create(['portal_user_id' => $user->id, 'email' => $user->email, 'event' => 'login.two_factor_pending', 'successful' => true, 'ip_address' => $request->ip(), 'user_agent' => str($request->userAgent())->limit(1000), 'created_at' => now()]);
            Auth::guard('portal')->logout();
            return redirect()->route('portal.two-factor.challenge');
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        PortalLoginActivity::create(['portal_user_id' => $user->id, 'email' => $user->email, 'event' => 'login.succeeded', 'successful' => true, 'ip_address' => $request->ip(), 'user_agent' => str($request->userAgent())->limit(1000), 'created_at' => now()]);

        return redirect()->intended(route('portal.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user('portal');
        PortalUserSession::where('session_hash', hash('sha256', $request->session()->getId()))->update(['revoked_at' => now()]);
        if ($user) PortalLoginActivity::create(['portal_user_id' => $user->id, 'email' => $user->email, 'event' => 'logout', 'successful' => true, 'ip_address' => $request->ip(), 'user_agent' => str($request->userAgent())->limit(1000), 'created_at' => now()]);
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
