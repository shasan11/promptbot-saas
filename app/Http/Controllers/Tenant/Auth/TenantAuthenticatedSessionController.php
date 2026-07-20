<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantAuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        abort_unless(tenancy()->initialized, 404);

        return Inertia::render('Auth/Login', [
            'canResetPassword' => false,
            'loginRoute' => 'tenant.login',
            'panelName' => 'Tenant Admin',
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(tenancy()->initialized, 404);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $key = 'tenant|'.tenant('id').'|'.Str::lower($request->string('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        if (! Auth::guard('tenant')->attempt(['email' => $credentials['email'], 'password' => $credentials['password']], (bool) ($credentials['remember'] ?? false))) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('tenant.admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('tenant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login');
    }
}
