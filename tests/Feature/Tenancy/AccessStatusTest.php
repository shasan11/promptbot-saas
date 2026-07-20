<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_domain_middleware_blocks_unknown_hosts(): void
    {
        config()->set('tenancy.central_domains', ['central.test']);

        $this->get('http://tenant.test/dashboard')->assertNotFound();
    }

    public function test_suspended_tenant_is_not_active(): void
    {
        $tenant = Tenant::create([
            'id' => 'acme',
            'company_name' => 'Acme',
            'slug' => 'acme',
            'status' => TenantStatus::Suspended,
        ]);

        $this->assertFalse($tenant->isActive());
    }
}
