<?php

namespace App\Http\Middleware;

use App\Services\SaaS\TenantFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantLimit
{
    public function handle(Request $request, Closure $next, string $feature, int $amount = 1): Response
    {
        if (! app(TenantFeatureService::class)->canConsume($feature, $amount)) {
            abort(403, 'Tenant feature limit has been reached.');
        }

        return $next($request);
    }
}
