<?php

namespace Tests\Feature\Tenant\Administration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    public function test_last_tenant_owner_cannot_be_suspended_even_by_another_administrator(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        [$owner, $admin] = $this->createTenantUsers($tenant, [
            ['attributes' => ['name' => 'Sole Owner'], 'role' => 'Tenant Owner'],
            ['attributes' => ['name' => 'An Administrator'], 'role' => 'Tenant Administrator'],
        ]);

        $response = $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/administration/users/{$owner->id}/suspend");

        $response->assertForbidden();

        tenancy()->initialize($tenant);
        $this->assertEquals('active', User::find($owner->id)->status->value);
        tenancy()->end();
    }

    public function test_owner_can_be_suspended_once_a_second_owner_exists(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        [$ownerOne, $ownerTwo] = $this->createTenantUsers($tenant, [
            ['attributes' => ['name' => 'Owner One'], 'role' => 'Tenant Owner'],
            ['attributes' => ['name' => 'Owner Two'], 'role' => 'Tenant Owner'],
        ]);

        $response = $this->actingAs($ownerTwo, 'tenant')
            ->post("http://{$domain}/administration/users/{$ownerOne->id}/suspend");

        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertEquals('suspended', User::find($ownerOne->id)->status->value);
        tenancy()->end();
    }

    public function test_user_without_permission_cannot_view_administration_users(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $viewer = $this->createTenantUser($tenant, ['name' => 'No Access'], null);

        $response = $this->actingAs($viewer, 'tenant')
            ->get("http://{$domain}/administration/users");

        $response->assertForbidden();
    }
}
