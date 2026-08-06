<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revokes an already-authenticated central session the moment the
 * administrator is deactivated, locked, or deleted elsewhere. Login-time
 * checks alone are not enough because an admin can be deactivated while
 * they still hold a live session.
 */
class EnsureCentralUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('central');

        if ($user && (! $user->is_active || $user->locked_until?->isFuture())) {
            Auth::guard('central')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('Your administrator account is no longer active. Contact a platform owner.'),
            ]);
        }

        return $next($request);
    }
}
