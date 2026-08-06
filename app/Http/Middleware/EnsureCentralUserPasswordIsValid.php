<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces an administrator whose password has expired to change it before
 * reaching any other Superadmin screen. The password itself remains valid
 * for authentication (expiry is a "must change" gate, not a lockout).
 */
class EnsureCentralUserPasswordIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('central');

        if ($user?->password_expires_at?->isPast()) {
            return redirect()->route('profile.edit')->with('status', 'password-expired');
        }

        return $next($request);
    }
}
