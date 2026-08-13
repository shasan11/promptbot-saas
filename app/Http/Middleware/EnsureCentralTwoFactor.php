<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('central');
        if (! $user) {
            return $next($request);
        }
        if ($user->two_factor_required && ! $user->two_factor_enabled) {
            return redirect()->route('superadmin.security.two-factor.setup')
                ->with('error', 'Two-factor authentication is required before you can continue.');
        }
        if ($user->two_factor_enabled && ! $request->session()->boolean('central.two_factor_confirmed')) {
            auth('central')->logout();
            $request->session()->put('central.two_factor_user_id', $user->id);
            return redirect()->route('two-factor.challenge');
        }
        return $next($request);
    }
}
