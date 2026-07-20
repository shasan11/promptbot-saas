<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if (! in_array($host, config('tenancy.central_domains', []), true)) {
            abort(404, 'Unknown platform domain.');
        }

        return $next($request);
    }
}
