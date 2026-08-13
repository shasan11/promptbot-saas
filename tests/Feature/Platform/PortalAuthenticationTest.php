<?php

namespace Tests\Feature\Platform;

use App\Enums\PortalUserStatus;
use App\Models\CustomerAccount;
use App\Models\PortalUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Services\Platform\TotpService;

class PortalAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_separate_portal_identity_and_owned_account(): void
    {
        Notification::fake();

        $response = $this->post(route('portal.register.store'), [
            'name' => 'Ada Owner',
            'email' => 'ada@example.test',
            'password' => 'Correct-Horse-Battery-99',
            'password_confirmation' => 'Correct-Horse-Battery-99',
            'account_name' => 'Ada Labs',
            'timezone' => 'UTC',
            'plan' => null,
            'interval' => 'monthly',
        ]);

        $user = PortalUser::where('email', 'ada@example.test')->firstOrFail();
        $account = CustomerAccount::where('name', 'Ada Labs')->firstOrFail();

        $response->assertRedirect(route('portal.workspaces.create'));
        $this->assertAuthenticatedAs($user, 'portal');
        $this->assertSame($account->id, session('portal.active_customer_account_id'));
        $this->assertDatabaseHas('customer_account_users', [
            'customer_account_id' => $account->id,
            'portal_user_id' => $user->id,
            'role' => 'owner',
            'can_manage_billing' => true,
        ]);
        $this->assertDatabaseMissing('central_users', ['email' => 'ada@example.test']);
    }

    public function test_suspended_portal_users_are_rejected_before_account_resolution(): void
    {
        $user = PortalUser::factory()->create([
            'status' => PortalUserStatus::Suspended,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user, 'portal')->get(route('portal.dashboard'))->assertForbidden();
    }

    public function test_portal_login_authenticates_and_records_activity(): void
    {
        $user = PortalUser::factory()->create(['password' => 'password']);
        $this->post(route('portal.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard', absolute: false));
        $this->assertAuthenticatedAs($user, 'portal');
        $this->assertDatabaseHas('portal_login_activities', ['portal_user_id' => $user->id, 'event' => 'login.succeeded', 'successful' => true]);
    }

    public function test_two_factor_login_requires_and_accepts_a_totp_code(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $user = PortalUser::factory()->create(['password' => 'password', 'two_factor_enabled' => true, 'two_factor_secret' => $secret, 'two_factor_recovery_codes' => []]);

        $this->post(route('portal.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('portal.two-factor.challenge'));
        $this->assertGuest('portal');
        $this->post(route('portal.two-factor.store'), ['code' => $totp->currentCode($secret)])
            ->assertRedirect(route('portal.dashboard', absolute: false));
        $this->assertAuthenticatedAs($user, 'portal');
    }

    public function test_failed_portal_login_is_recorded_without_authentication(): void
    {
        $user = PortalUser::factory()->create(['password' => 'password']);
        $this->post(route('portal.login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest('portal');
        $this->assertDatabaseHas('portal_login_activities', ['portal_user_id' => $user->id, 'event' => 'login.failed', 'successful' => false]);
    }
}
