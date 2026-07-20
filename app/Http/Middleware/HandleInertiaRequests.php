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
            $permissions = $user->role === 'super_admin' || $user->email === env('CENTRAL_ADMIN_EMAIL', 'admin@example.com')
                ? ['*']
                : $user->getAllPermissions()->pluck('name')->values()->all();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'guard' => $guard,
                'user' => $user,
                'permissions' => $permissions,
                'can' => [
                    'viewDashboard' => in_array('*', $permissions, true) || in_array('dashboard.view', $permissions, true),
                    'viewTenants' => in_array('*', $permissions, true) || in_array('tenants.view', $permissions, true),
                    'viewPlans' => in_array('*', $permissions, true) || in_array('plans.view', $permissions, true),
                    'viewSubscriptions' => in_array('*', $permissions, true) || in_array('subscriptions.view', $permissions, true),
                    'viewFeatures' => in_array('*', $permissions, true) || in_array('features.view', $permissions, true),
                    'viewSettings' => in_array('*', $permissions, true) || in_array('settings.view', $permissions, true),
                    'viewAuditLogs' => in_array('*', $permissions, true) || in_array('audit_logs.view', $permissions, true),
                    'viewOperations' => in_array('*', $permissions, true) || in_array('operations.view', $permissions, true),
                    'viewWebsite' => in_array('*', $permissions, true) || in_array('website.view', $permissions, true),
                    'viewPayments' => in_array('*', $permissions, true) || in_array('payments.view', $permissions, true),
                    'viewCommunications' => in_array('*', $permissions, true) || in_array('communications.view', $permissions, true),
                    'viewSupport' => in_array('*', $permissions, true) || in_array('support.view', $permissions, true),
                    'viewIntegrations' => in_array('*', $permissions, true) || in_array('integrations.view', $permissions, true),
                ],
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
