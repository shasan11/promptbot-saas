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

    public function test_seeded_tenant_administrator_can_access_administration_settings_modules(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain('cortifox');
        $admin = $this->createTenantUser($tenant, ['name' => 'Cortifox Admin'], 'Tenant Administrator');

        foreach ([
            "http://{$domain}/administration",
            "http://{$domain}/administration/workspace/general",
            "http://{$domain}/administration/business-hours",
            "http://{$domain}/administration/holidays",
        ] as $url) {
            $this->actingAs($admin, 'tenant')->get($url)->assertOk();
        }
    }

    public function test_tenant_authorization_seeder_repairs_legacy_owner_role_assignments(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        try {
            $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
            $legacyRole = \App\Models\TenantRole::firstOrCreate(
                ['name' => 'tenant_owner', 'guard_name' => 'tenant'],
                ['label' => 'Tenant Owner']
            );
            $user->assignRole($legacyRole);

            $this->assertFalse($user->can('users.view'));

            $this->seed(\Database\Seeders\TenantAuthorizationSeeder::class);

            $user->refresh();
            $this->assertTrue($user->hasRole('Tenant Owner'));
            $this->assertTrue($user->can('users.view'));
            $this->assertTrue($user->can('workspace.view'));
        } finally {
            tenancy()->end();
        }
    }
}
