<?php

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

/**
 * Every readable Superadmin module must be reachable with its exact
 * permission and forbidden without it. This is the regression guard for the
 * authorization gap where `auth:central` alone used to be sufficient to
 * reach any Superadmin page regardless of assigned role/permissions.
 */
class SuperadminRoutePermissionsTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public static function routePermissions(): array
    {
        return [
            'dashboard' => ['superadmin.dashboard', 'dashboard.view'],
            'payments' => ['superadmin.billing.payments.index', 'payments.view'],
            'invoices' => ['superadmin.billing.invoices.index', 'invoices.view'],
            'coupons' => ['superadmin.billing.coupons.index', 'coupons.view'],
            'gateways' => ['superadmin.billing.gateways.index', 'gateways.manage'],
            'usage' => ['superadmin.platform.usage.index', 'usage.view'],
            'integrations' => ['superadmin.platform.integrations.index', 'integrations.view'],
            'website' => ['superadmin.website.index', 'website.view'],
            'communications' => ['superadmin.communications.index', 'communications.view'],
            'support' => ['superadmin.support.index', 'support.view'],
            'operations' => ['superadmin.operations.health', 'operations.view'],
            'audit logs' => ['superadmin.system.audit-logs.index', 'audit_logs.view'],
            'login attempts' => ['superadmin.system.login-attempts.index', 'login_attempts.view'],
            'administrators' => ['superadmin.system.administrators.index', 'administrators.view'],
            'roles' => ['superadmin.system.roles.index', 'roles.manage'],
            'settings' => ['superadmin.system.settings.index', 'settings.view'],
            'security' => ['superadmin.system.security.index', 'security.manage'],
            'plans' => ['superadmin.plans.index', 'plans.view'],
            'features' => ['superadmin.features.index', 'features.view'],
            'subscriptions' => ['superadmin.subscriptions.index', 'subscriptions.view'],
            'tenants' => ['superadmin.tenants.index', 'tenants.view'],
        ];
    }

    #[DataProvider('routePermissions')]
    public function test_route_requires_its_exact_permission(string $routeName, string $permission): void
    {
        $this->actingAs($this->centralAdminWithPermissions([]), 'central')
            ->get(route($routeName))
            ->assertForbidden();

        $this->actingAs($this->centralAdminWithPermissions([$permission]), 'central')
            ->get(route($routeName))
            ->assertOk();
    }

    #[DataProvider('routePermissions')]
    public function test_route_rejects_unauthenticated_requests(string $routeName): void
    {
        $this->get(route($routeName))
            ->assertRedirect(route('login'));
    }

    public function test_platform_owner_can_reach_every_module(): void
    {
        $owner = $this->platformOwner();

        foreach (self::routePermissions() as [$routeName, $permission]) {
            $this->actingAs($owner, 'central')->get(route($routeName))->assertOk();
        }
    }

    public function test_read_only_auditor_can_view_but_not_manage(): void
    {
        $auditor = $this->readOnlyAuditor();

        $this->actingAs($auditor, 'central')
            ->get(route('superadmin.tenants.index'))
            ->assertOk();

        $this->actingAs($auditor, 'central')
            ->get(route('superadmin.tenants.create'))
            ->assertForbidden();

        // Read-Only Auditor only receives permissions ending in `.view`, so
        // gateways/roles/security (which have no `.view` permission in the
        // platform's permission set) remain inaccessible to it by design.
        $this->actingAs($auditor, 'central')
            ->get(route('superadmin.billing.gateways.index'))
            ->assertForbidden();
    }
}
