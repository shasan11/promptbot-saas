<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Http\Requests\Admin\TenantStoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_admin_can_view_tenants(): void
    {
        $this->actingAs($this->centralUserWithPermissions('tenants.view'), 'central')
            ->get(route('superadmin.tenants.index'))
            ->assertOk();
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
