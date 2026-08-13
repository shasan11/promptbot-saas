<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'panelName' => 'Super Admin',
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $user = Auth::guard('central')->user();
        if ($user?->two_factor_enabled) {
            $request->session()->put('central.two_factor_user_id', $user->id);
            $request->session()->put('central.two_factor_remember', $request->boolean('remember'));
            Auth::guard('central')->logout();
            return redirect()->route('two-factor.challenge');
        }
        $request->session()->put('central.two_factor_confirmed', true);
        app(AuditLogService::class)->record('platform_admin.login');

        return redirect()->intended(route('superadmin.dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        app(AuditLogService::class)->record('platform_admin.logout');
        Auth::guard('central')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
