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
            'tickets' => ['superadmin.tickets.index', 'support.view'],
            'reports' => ['superadmin.reports.index', 'dashboard.view'],
            'operations' => ['superadmin.operations.health', 'operations.view'],
            'website' => ['superadmin.website.index', 'website.view'],
            'settings' => ['superadmin.system.settings.index', 'settings.view'],
            'plans' => ['superadmin.plans.index', 'plans.view'],
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
            ->get(route('superadmin.billing.payments.index'))
            ->assertOk();

        $this->actingAs($auditor, 'central')
            ->get(route('superadmin.billing.payments.create'))
            ->assertForbidden();

        $this->actingAs($auditor, 'central')
            ->get(route('superadmin.tenants.create'))
            ->assertForbidden();
    }
}
