<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralAdministratorIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('central');

        if (! $user) {
            return $next($request);
        }

        $blocked = ! $user->is_active
            || $user->suspended_at !== null
            || $user->locked_until?->isFuture()
            || $user->password_expires_at?->isPast();

        if ($blocked) {
            Auth::guard('central')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'This administrator account is not active.');
        }

        return $next($request);
    }
}
