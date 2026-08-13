<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\Platform\PlatformSettingsService;
use App\Models\PortalNotification;
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
        $guard = tenancy()->initialized
            ? 'tenant'
            : ($request->user('portal') ? 'portal' : 'central');
        $user = $request->user($guard);
        $permissions = $user && method_exists($user, 'getAllPermissions')
            ? $user->getAllPermissions()->pluck('name')->values()->all()
            : [];
        $activeAccount = $request->attributes->get('customerAccount');

        return [
            ...parent::share($request),
            'auth' => [
                'guard' => $guard,
                'user' => $user,
                'permissions' => $permissions,
            ],
            'portal' => fn () => $guard === 'portal' ? [
                'activeAccount' => $activeAccount,
                'accounts' => $user->accounts()->orderBy('name')->get(['customer_accounts.id', 'public_uuid', 'name', 'status']),
                'membership' => $activeAccount?->pivot,
                'unreadNotifications' => $activeAccount ? PortalNotification::where('customer_account_id', $activeAccount->id)
                    ->where(fn ($query) => $query->whereNull('portal_user_id')->orWhere('portal_user_id', $user->id))->whereNull('read_at')->count() : 0,
                'features' => [
                    'workspaceCreation' => filter_var(app(PlatformSettingsService::class)->get('customer_portal', 'allow_workspace_creation', true), FILTER_VALIDATE_BOOL),
                    'memberInvitations' => filter_var(app(PlatformSettingsService::class)->get('customer_portal', 'allow_member_invitations', true), FILTER_VALIDATE_BOOL),
                    'support' => filter_var(app(PlatformSettingsService::class)->get('customer_portal', 'support_tickets_enabled', app(PlatformSettingsService::class)->get('customer_portal', 'allow_support_tickets', true)), FILTER_VALIDATE_BOOL),
                    'planChanges' => filter_var(app(PlatformSettingsService::class)->get('customer_portal', 'allow_plan_changes', true), FILTER_VALIDATE_BOOL),
                    'cancellations' => filter_var(app(PlatformSettingsService::class)->get('customer_portal', 'allow_cancellations', true), FILTER_VALIDATE_BOOL),
                ],
            ] : null,
            'platform' => function (): array {
                $settings = app(PlatformSettingsService::class);
                return [
                    ...$settings->publicBranding(),
                    'maintenanceBanner' => filter_var($settings->get('maintenance', 'banner_enabled', false), FILTER_VALIDATE_BOOL)
                        ? (string) $settings->get('maintenance', 'banner_message', '') : null,
                ];
            },
            'tenant' => fn () => tenancy()->initialized ? [
                'id' => tenant('id'),
                'companyName' => Setting::query()->where('key', 'general.workspace_name')->value('value')['value'] ?? tenant('company_name'),
                'logoUrl' => Setting::query()->where('key', 'branding.logo')->value('value')['value'] ?? null,
            ] : null,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                'channel_secret' => fn () => $request->session()->get('channel_secret'),
                'api_key' => fn () => $request->session()->get('api_key'),
                'webhook_secret' => fn () => $request->session()->get('webhook_secret'),
                'portal_url' => fn () => $request->session()->get('portal_url'),
                'oauth_authorization' => fn () => $request->session()->get('oauth_authorization'),
            ],
        ];
    }
}
