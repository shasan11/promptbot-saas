<?php

namespace Tests\Feature\Tenant\Administration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    public function test_tenant_a_cannot_see_tenant_b_users_in_the_administration_module(): void
    {
        [$tenantA, $domainA] = $this->createTenantWithDomain();
        [$tenantB, $domainB] = $this->createTenantWithDomain();

        $userA = $this->createTenantUser($tenantA, ['name' => 'Alice From A']);
        $this->createTenantUser($tenantB, ['name' => 'Bob From B']);

        $response = $this->actingAs($userA, 'tenant')
            ->get("http://{$domainA}/administration/users");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('users.data.0.name', fn ($name) => str_contains($name, 'Alice') || $name === $userA->name)
        );

        $names = collect($response->viewData('page')['props']['users']['data'])->pluck('name')->all();
        $this->assertContains('Alice From A', $names);
        $this->assertNotContains('Bob From B', $names);
    }
}
