<?php

namespace App\Http\Middleware;

use App\Services\Installer\TenancyInstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Once installation has completed (storage/installed marker written), the
 * installer endpoints have no legitimate use and only expose filesystem
 * checks and a raw MySQL connectivity probe. Block them permanently.
 */
class EnsureInstallerIsOpen
{
    public function __construct(private readonly TenancyInstallationService $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->installed()) {
            abort(404);
        }

        return $next($request);
    }
}
