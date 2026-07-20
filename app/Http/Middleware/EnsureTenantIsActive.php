<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant || $tenant->status !== TenantStatus::Active) {
            abort(423, 'Tenant is not active.');
        }

        return $next($request);
    }
}
