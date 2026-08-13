<?php

namespace App\Http\Middleware;

use App\Services\Platform\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalEmailIsVerified
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $required = filter_var($this->settings->get('registration', 'email_verification_required', true), FILTER_VALIDATE_BOOL);
        if ($required && ! $request->user('portal')?->hasVerifiedEmail()) {
            return redirect()->route('portal.verification.notice');
        }
        return $next($request);
    }
}
