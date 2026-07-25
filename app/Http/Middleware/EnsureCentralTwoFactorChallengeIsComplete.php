<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralTwoFactorChallengeIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('central');

        if (! $user || ! $user->mustCompleteTwoFactorChallenge()) {
            return $next($request);
        }

        if ($request->session()->boolean('central.2fa.passed')) {
            return $next($request);
        }

        if ($request->routeIs('superadmin.two-factor.*') || $request->is('superadmin/two-factor-challenge')) {
            return $next($request);
        }

        return redirect()->route('superadmin.two-factor.challenge');
    }
}
