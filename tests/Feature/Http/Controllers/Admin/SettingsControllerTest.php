<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\PlatformSetting;
use App\Services\Platform\SecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->centralAdminWithPermissions([]), 'central')
            ->get(route('superadmin.system.settings.index'))
            ->assertForbidden();
    }

    public function test_admin_with_view_permission_can_view_settings(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['settings.view']), 'central')
            ->get(route('superadmin.system.settings.index'))
            ->assertOk();
    }

    public function test_view_permission_does_not_grant_update_access(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['settings.view']), 'central')
            ->put(route('superadmin.system.settings.update', 'general'), ['platform_name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_admin_can_update_general_settings(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['settings.update']), 'central')
            ->put(route('superadmin.system.settings.update', 'general'), [
                'platform_name' => 'Acme Cloud',
                'support_email' => 'help@acme.test',
            ])
            ->assertRedirect();

        $this->assertSame('Acme Cloud', data_get(
            PlatformSetting::query()->where('group', 'general')->where('key', 'platform_name')->first()?->value,
            'value'
        ));
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform_settings.updated']);
    }

    public function test_updating_security_settings_takes_effect_immediately(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['settings.update']), 'central')
            ->put(route('superadmin.system.settings.update', 'security'), [
                'login_attempt_limit' => 3,
                'lockout_duration_minutes' => 30,
                'password_expiry_days' => 60,
            ])
            ->assertRedirect();

        $security = app(SecuritySettings::class);
        $this->assertSame(3, $security->loginAttemptLimit());
        $this->assertSame(30, $security->lockoutDurationMinutes());
        $this->assertSame(60, $security->passwordExpiryDays());
    }

    public function test_unknown_setting_group_is_not_found(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['settings.update']), 'central')
            ->put(route('superadmin.system.settings.update', 'not-a-real-group'), [])
            ->assertNotFound();
    }
}
