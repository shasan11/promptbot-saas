<?php

namespace Tests\Feature\Installer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TenancyInstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(storage_path('installed'));

        parent::tearDown();
    }

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

    public function test_installer_endpoints_are_unavailable_once_installed(): void
    {
        File::put(storage_path('installed'), now()->toIso8601String());

        $this->getJson('/install/tenancy/status')->assertNotFound();
        $this->postJson('/install/tenancy/license', ['purchase_code' => 'demo'])->assertNotFound();
        $this->postJson('/install/tenancy/tenant-provisioning', ['mode' => 'manual'])->assertNotFound();
    }

    public function test_installer_endpoints_are_unavailable_on_unknown_hosts(): void
    {
        $this->getJson('http://not-a-central-domain.test/install/tenancy/status')
            ->assertNotFound();
    }
}
