<?php

namespace Tests\Feature\Installer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyInstallerTest extends TestCase
{
    use RefreshDatabase;

    public function test_installer_tenancy_status_is_available(): void
    {
        $this->getJson('/install/tenancy/status')
            ->assertOk()
            ->assertJsonStructure(['installed', 'requirements', 'permissions', 'tenant_provisioning_mode']);
    }

    public function test_license_validation_is_successful_when_disabled(): void
    {
        config()->set('installer.license.enabled', false);

        $this->postJson('/install/tenancy/license', ['purchase_code' => 'demo'])
            ->assertOk()
            ->assertJson(['valid' => true]);
    }
}
