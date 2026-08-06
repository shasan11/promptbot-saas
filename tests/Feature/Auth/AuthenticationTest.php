<?php

namespace Tests\Feature\Auth;

use App\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/superadmin/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = CentralUser::factory()->create();

        $response = $this->post('/superadmin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('superadmin.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = CentralUser::factory()->create();

        $this->post('/superadmin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = CentralUser::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_inactive_administrator_cannot_authenticate(): void
    {
        $user = CentralUser::factory()->inactive()->create();

        $this->post('/superadmin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest('central');
        $this->assertDatabaseHas('platform_admin_login_attempts', [
            'email' => $user->email,
            'successful' => false,
            'failure_reason' => 'inactive',
        ]);
    }

    public function test_locked_administrator_cannot_authenticate(): void
    {
        $user = CentralUser::factory()->locked()->create();

        $this->post('/superadmin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest('central');
        $this->assertDatabaseHas('platform_admin_login_attempts', [
            'email' => $user->email,
            'successful' => false,
            'failure_reason' => 'locked',
        ]);
    }

    public function test_deactivating_an_administrator_revokes_their_active_session(): void
    {
        $user = $this->centralAdminWithPermissions(['dashboard.view']);

        $this->actingAs($user, 'central')
            ->get(route('superadmin.dashboard'))
            ->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('superadmin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest('central');
    }

    public function test_successful_login_is_recorded(): void
    {
        $user = CentralUser::factory()->create();

        $this->post('/superadmin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('platform_admin_login_attempts', [
            'email' => $user->email,
            'successful' => true,
        ]);
    }

    public function test_failed_login_is_recorded(): void
    {
        $user = CentralUser::factory()->create();

        $this->post('/superadmin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('platform_admin_login_attempts', [
            'email' => $user->email,
            'successful' => false,
            'failure_reason' => 'invalid_credentials',
        ]);
    }
}
