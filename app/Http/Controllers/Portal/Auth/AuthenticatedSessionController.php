<?php

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalLoginRequest;
use App\Models\PortalLoginActivity;
use App\Models\PortalUserSession;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\PortalAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(PlatformSettingsService $settings): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'loginRoute' => 'portal.login.store',
            'passwordRequestRoute' => 'portal.password.request',
            'panelName' => 'Customer Portal',
            'status' => session('status'),
            'googleAuth' => $this->googleAuth($settings, 'login'),
        ]);
    }

    public function store(PortalLoginRequest $request, PortalAuthenticationService $authentication): RedirectResponse
    {
        $request->authenticate();
        $user = $request->user('portal');

        return $authentication->finish($request, $user, $request->boolean('remember'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user('portal');
        PortalUserSession::where('session_hash', hash('sha256', $request->session()->getId()))->update(['revoked_at' => now()]);
        if ($user) {
            PortalLoginActivity::create(['portal_user_id' => $user->id, 'email' => $user->email, 'event' => 'logout', 'successful' => true, 'ip_address' => $request->ip(), 'user_agent' => str($request->userAgent())->limit(1000), 'created_at' => now()]);
        }
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    private function googleAuth(PlatformSettingsService $settings, string $intent): array
    {
        $enabled = filter_var($settings->get('customer_portal', 'google_login_enabled', false), FILTER_VALIDATE_BOOL);
        $configured = filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'));

        return ['enabled' => $enabled && $configured, 'configured' => $configured, 'url' => route('portal.oauth.google.redirect', ['intent' => $intent])];
    }
}
