<?php

namespace App\Http\Middleware;

use App\Enums\PortalUserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('portal');

        abort_unless($user && $user->status === PortalUserStatus::Active, 403, 'This portal account is not active.');

        return $next($request);
    }
}
