<?php

namespace App\Http\Middleware;

use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $guard = tenancy()->initialized ? 'tenant' : 'central';
        $user = tenancy()->initialized ? $request->user('tenant') : $request->user('central');
        $permissions = [];

        if ($guard === 'central' && $user) {
            $permissions = $user->getAllPermissions()->pluck('name')->values()->all();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'guard' => $guard,
                'user' => $user,
                'permissions' => $permissions,
            ],
            'platform' => fn () => app(PlatformSettingsService::class)->publicBranding(),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
