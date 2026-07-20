<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\CentralUser;
use App\Models\PlatformAdminLoginAttempt;
use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_platform_tables_use_uuid_primary_keys(): void
    {
        $role = PlatformRole::create(['name' => 'Security Administrator', 'guard_name' => 'central']);
        $permission = PlatformPermission::create(['name' => 'security.manage', 'guard_name' => 'central']);
        $setting = PlatformSetting::create(['group' => 'security', 'key' => 'session_duration', 'value' => ['minutes' => 120]]);
        $auditLog = AuditLog::create(['action' => 'platform.test']);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $role->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $permission->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $setting->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $auditLog->id);
    }

    public function test_existing_tenant_records_receive_public_uuid_route_keys(): void
    {
        $tenant = Tenant::create([
            'id' => 'public-route-key',
            'company_name' => 'Public Route Key',
            'slug' => 'public-route-key',
            'status' => 'active',
        ]);

        $this->assertNotSame($tenant->id, $tenant->getRouteKey());
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $tenant->public_uuid);
        $this->assertSame('public_uuid', $tenant->getRouteKeyName());
    }

    public function test_superadmin_login_records_attempt_and_audit_log(): void
    {
        $user = CentralUser::factory()->create();

        $this->post('/superadmin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('superadmin.dashboard', absolute: false));

        $this->assertDatabaseHas('platform_admin_login_attempts', [
            'administrator_id' => $user->id,
            'email' => $user->email,
            'successful' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'administrator_id' => $user->id,
            'action' => 'platform_admin.login',
        ]);
    }

    public function test_failed_superadmin_login_records_failure(): void
    {
        $user = CentralUser::factory()->create();

        $this->post('/superadmin/login', [
            'email' => $user->email,
            'password' => 'not-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('platform_admin_login_attempts', [
            'administrator_id' => $user->id,
            'email' => $user->email,
            'successful' => false,
            'failure_reason' => 'invalid_credentials',
        ]);
    }
}
