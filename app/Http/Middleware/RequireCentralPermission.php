<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCentralPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user('central');

        abort_unless($user?->can($permission), 403);

        return $next($request);
    }
}
