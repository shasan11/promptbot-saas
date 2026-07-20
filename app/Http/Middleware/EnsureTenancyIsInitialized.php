<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenancyIsInitialized
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            abort(404, 'Tenant context was not initialized.');
        }

        return $next($request);
    }
}
