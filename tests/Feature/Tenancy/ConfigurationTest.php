<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_domains_are_parsed_from_environment_config(): void
    {
        config()->set('tenancy.central_domains', ['example.com', 'www.example.com']);

        $this->assertContains('example.com', config('tenancy.central_domains'));
        $this->assertContains('www.example.com', config('tenancy.central_domains'));
    }

    public function test_tenant_model_hides_database_passwords(): void
    {
        $tenant = Tenant::create([
            'id' => 'acme',
            'company_name' => 'Acme',
            'slug' => 'acme',
            'tenancy_db_password' => 'secret',
        ]);

        $this->assertArrayNotHasKey('tenancy_db_password', $tenant->toArray());
    }
}
