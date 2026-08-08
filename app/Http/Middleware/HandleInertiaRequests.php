<?php

namespace App\Http\Middleware;

use App\Models\Setting;
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
        $permissions = $user ? $user->getAllPermissions()->pluck('name')->values()->all() : [];

        return [
            ...parent::share($request),
            'auth' => [
                'guard' => $guard,
                'user' => $user,
                'permissions' => $permissions,
            ],
            'platform' => fn () => app(PlatformSettingsService::class)->publicBranding(),
            'tenant' => fn () => tenancy()->initialized ? [
                'id' => tenant('id'),
                'companyName' => Setting::query()->where('key', 'general.workspace_name')->value('value')['value'] ?? tenant('company_name'),
                'logoUrl' => Setting::query()->where('key', 'branding.logo')->value('value')['value'] ?? null,
            ] : null,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
