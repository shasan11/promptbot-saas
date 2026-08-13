<?php

namespace App\Http\Middleware;

use App\Models\PortalUserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackPortalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('portal');
        $hash = hash('sha256', $request->session()->getId());
        $session = PortalUserSession::where('session_hash', $hash)->first();

        if ($session?->revoked_at) {
            Auth::guard('portal')->logout();
            $request->session()->invalidate();
            return redirect()->route('portal.login')->withErrors(['email' => 'This session was revoked.']);
        }

        PortalUserSession::updateOrCreate(['session_hash' => $hash], [
            'portal_user_id' => $user->getKey(), 'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent())->limit(1000), 'last_activity_at' => now(), 'revoked_at' => null,
        ]);

        return $next($request);
    }
}
