<?php

namespace App\Http\Middleware;

use App\Services\SaaS\TenantFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! app(TenantFeatureService::class)->enabled($feature)) {
            abort(403, 'This feature is not available for the tenant.');
        }

        return $next($request);
    }
}
