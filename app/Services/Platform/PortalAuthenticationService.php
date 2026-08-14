<?php

namespace App\Services\Platform;

use App\Enums\PortalUserStatus;
use App\Models\PortalLoginActivity;
use App\Models\PortalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalAuthenticationService
{
    public function finish(Request $request, PortalUser $user, bool $remember = false, string $provider = 'password', ?string $destination = null): RedirectResponse
    {
        if ($user->status !== PortalUserStatus::Active) {
            $this->activity($request, $user, 'login.failed', false, $provider, ['reason' => 'inactive']);
            Auth::guard('portal')->logout();

            return redirect()->route('portal.login')->withErrors(['email' => 'This portal account is not active.']);
        }

        Auth::guard('portal')->login($user, $remember);
        $request->session()->regenerate();

        if ($user->two_factor_enabled) {
            $request->session()->put('portal.two_factor_user_id', $user->getKey());
            $request->session()->put('portal.two_factor_remember', $remember);
            $request->session()->put('portal.two_factor_metadata', ['provider' => $provider]);
            if ($destination) {
                $request->session()->put('portal.post_auth_redirect', $destination);
            }
            $this->activity($request, $user, 'login.two_factor_pending', true, $provider);
            Auth::guard('portal')->logout();

            return redirect()->route('portal.two-factor.challenge');
        }

        $this->recordSuccess($request, $user, $provider);

        return $destination
            ? redirect()->to($destination)
            : redirect()->intended(route('portal.dashboard', absolute: false));
    }

    public function recordSuccess(Request $request, PortalUser $user, string $provider, array $metadata = []): void
    {
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->activity($request, $user, 'login.succeeded', true, $provider, $metadata);
    }

    private function activity(Request $request, PortalUser $user, string $event, bool $successful, string $provider, array $metadata = []): void
    {
        PortalLoginActivity::create([
            'portal_user_id' => $user->id, 'email' => $user->email, 'event' => $event, 'successful' => $successful,
            'ip_address' => $request->ip(), 'user_agent' => str($request->userAgent())->limit(1000),
            'metadata' => ['provider' => $provider, ...$metadata], 'created_at' => now(),
        ]);
    }
}
