<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Http\Requests\Admin\TenantStoreRequest;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class TenantControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->centralAdminWithPermissions([]), 'central')
            ->get(route('superadmin.tenants.index'))
            ->assertForbidden();
    }

    public function test_admin_with_view_permission_can_view_tenants(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['tenants.view']), 'central')
            ->get(route('superadmin.tenants.index'))
            ->assertOk();
    }

    public function test_view_permission_does_not_grant_create_access(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['tenants.view']), 'central')
            ->get(route('superadmin.tenants.create'))
            ->assertForbidden();
    }

    public function test_view_permission_does_not_grant_suspend_access(): void
    {
        $tenant = Tenant::create([
            'id' => 'tenant-suspend-test',
            'company_name' => 'Suspend Test',
            'slug' => 'tenant-suspend-test',
            'status' => 'active',
        ]);

        $this->actingAs($this->centralAdminWithPermissions(['tenants.view']), 'central')
            ->post(route('superadmin.tenants.suspend', $tenant), ['reason' => 'test'])
            ->assertForbidden();
    }

    public function test_manual_tenant_database_password_can_be_blank(): void
    {
        $validator = Validator::make([
            'company_name' => 'Blank Password Company',
            'slug' => 'blank-password-company',
            'owner_name' => 'Tenant Owner',
            'owner_email' => 'owner@example.com',
            'owner_password' => 'password1234',
            'provisioning_mode' => 'manual',
            'database_host' => '127.0.0.1',
            'database_port' => 3306,
            'database_name' => 'tenant_blank_password_company',
            'database_username' => 'root',
            'database_password' => '',
        ], (new TenantStoreRequest)->rules());

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }
}
