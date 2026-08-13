<?php

namespace App\Http\Middleware;

use App\Services\Platform\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = app(PlatformSettingsService::class)->get('customer_portal', 'enabled', true);
        abort_unless(filter_var($enabled, FILTER_VALIDATE_BOOL), 404);

        return $next($request);
    }
}
