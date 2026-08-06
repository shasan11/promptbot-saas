<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $guard = tenancy()->initialized ? 'tenant' : 'central';
        $user = tenancy()->initialized ? $request->user('tenant') : $request->user('central');
        $permissions = [];

        if ($guard === 'central' && $user) {
            // Spatie is the sole source of truth for authorization. This list must
            // reflect exactly what the backend will accept, so the UI never shows
            // a link a click would then 403 on.
            $permissions = $user->getAllPermissions()->pluck('name')->values()->all();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'guard' => $guard,
                'user' => $user,
                'permissions' => $permissions,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
