<?php

namespace Tests\Feature\Platform;

use App\Enums\PortalUserStatus;
use App\Models\CustomerAccount;
use App\Models\PlatformSetting;
use App\Models\PortalLoginActivity;
use App\Models\PortalSocialAccount;
use App\Models\PortalUser;
use App\Services\Platform\CustomerAccountService;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class PortalGoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google' => ['client_id' => 'client-id', 'client_secret' => 'client-secret', 'redirect' => 'http://localhost/account/oauth/google/callback']]);
        PlatformSetting::create(['group' => 'customer_portal', 'key' => 'google_login_enabled', 'value' => ['value' => true], 'encrypted' => false, 'is_sensitive' => false]);
        app(PlatformSettingsService::class)->clear();
    }

    public function test_customer_can_initiate_google_auth_without_changing_superadmin_routes(): void
    {
        Socialite::fake('google', $this->googleUser());
        $this->get(route('portal.oauth.google.redirect'))->assertRedirect('https://socialite.fake/google/authorize');
        $this->get('/superadmin/login')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login')->missing('googleAuth'));
    }

    public function test_existing_verified_email_is_linked_and_authenticated_only_on_portal_guard(): void
    {
        $user = PortalUser::factory()->create(['email' => 'owner@example.test']);
        $account = app(CustomerAccountService::class)->createWithOwner($user, ['name' => 'Owner Co']);
        Socialite::fake('google', $this->googleUser());

        $this->get(route('portal.oauth.google.callback'))->assertRedirect(route('portal.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user, 'portal');
        $this->assertGuest('central');
        $this->assertGuest('tenant');
        $this->assertSame($account->id, session('portal.active_customer_account_id'));
        $this->assertDatabaseHas('portal_social_accounts', ['portal_user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-123']);
        $this->assertDatabaseHas('portal_login_activities', ['portal_user_id' => $user->id, 'event' => 'login.succeeded', 'successful' => true]);
        $this->get(route('portal.dashboard'))->assertOk();
        $this->assertDatabaseHas('portal_user_sessions', ['portal_user_id' => $user->id, 'revoked_at' => null]);
    }

    public function test_new_google_customer_completes_account_bootstrap_and_preserves_plan_context(): void
    {
        Socialite::fake('google', $this->googleUser(['email' => 'new@example.test', 'name' => 'New Owner']));
        $this->withSession(['portal.oauth.context' => ['intent' => 'register', 'plan' => null, 'interval' => 'yearly']])
            ->get(route('portal.oauth.google.callback'))->assertRedirect(route('portal.oauth.google.onboarding'));

        $this->post(route('portal.oauth.google.complete'), ['account_name' => 'New Company', 'timezone' => 'UTC', 'plan' => null, 'interval' => 'yearly'])
            ->assertRedirect(route('portal.workspaces.create', absolute: false));

        $user = PortalUser::where('email', 'new@example.test')->firstOrFail();
        $account = CustomerAccount::where('name', 'New Company')->firstOrFail();
        $this->assertAuthenticatedAs($user, 'portal');
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(['plan' => null, 'interval' => 'yearly'], session('portal.purchase_selection'));
        $this->assertDatabaseHas('customer_account_users', ['customer_account_id' => $account->id, 'portal_user_id' => $user->id, 'role' => 'owner']);
        $this->assertDatabaseHas('billing_profiles', ['customer_account_id' => $account->id, 'billing_email' => 'new@example.test']);
    }

    public function test_new_google_customer_is_refused_when_registration_is_disabled(): void
    {
        PlatformSetting::where('group', 'customer_portal')->where('key', 'google_login_enabled')->firstOrFail();
        PlatformSetting::create(['group' => 'registration', 'key' => 'mode', 'value' => ['value' => 'disabled'], 'encrypted' => false, 'is_sensitive' => false]);
        Socialite::fake('google', $this->googleUser(['email' => 'blocked@example.test']));

        $this->get(route('portal.oauth.google.callback'))->assertRedirect(route('portal.login'))->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('portal_users', ['email' => 'blocked@example.test']);
        $this->assertDatabaseMissing('customer_accounts', ['name' => "Test User's Account"]);
    }

    public function test_inactive_user_cannot_link_or_authenticate_with_google(): void
    {
        $user = PortalUser::factory()->create(['email' => 'owner@example.test', 'status' => PortalUserStatus::Suspended]);
        Socialite::fake('google', $this->googleUser());
        $this->get(route('portal.oauth.google.callback'))->assertRedirect(route('portal.login'))->assertSessionHasErrors('email');
        $this->assertGuest('portal');
        $this->assertDatabaseMissing('portal_social_accounts', ['portal_user_id' => $user->id]);
    }

    public function test_missing_or_unverified_provider_email_fails_safely(): void
    {
        Socialite::fake('google', $this->googleUser(['email' => '', 'verified_email' => false]));
        $this->get(route('portal.oauth.google.callback'))->assertRedirect(route('portal.login'))->assertSessionHasErrors('email');
        $this->assertGuest('portal');
    }

    public function test_google_authentication_does_not_bypass_local_two_factor(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $user = PortalUser::factory()->create(['email' => 'owner@example.test', 'two_factor_enabled' => true, 'two_factor_secret' => $secret, 'two_factor_recovery_codes' => []]);
        app(CustomerAccountService::class)->createWithOwner($user, ['name' => 'Owner Co']);
        PortalSocialAccount::create(['portal_user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-123', 'provider_email' => $user->email]);
        Socialite::fake('google', $this->googleUser());

        $this->get(route('portal.oauth.google.callback'))->assertRedirect(route('portal.two-factor.challenge'));
        $this->assertGuest('portal');
        $this->post(route('portal.two-factor.store'), ['code' => $totp->currentCode($secret)])->assertRedirect(route('portal.dashboard', absolute: false));
        $this->assertAuthenticatedAs($user, 'portal');
        $activity = PortalLoginActivity::where('portal_user_id', $user->id)->where('event', 'login.succeeded')->latest('created_at')->firstOrFail();
        $this->assertSame('google', $activity->metadata['provider']);
        $this->assertTrue($activity->metadata['two_factor']);
    }

    public function test_provider_identity_cannot_be_linked_to_two_users_and_retry_is_idempotent(): void
    {
        $first = PortalUser::factory()->create(['email' => 'owner@example.test']);
        app(CustomerAccountService::class)->createWithOwner($first, ['name' => 'First']);
        PortalSocialAccount::create(['portal_user_id' => $first->id, 'provider' => 'google', 'provider_user_id' => 'google-123', 'provider_email' => $first->email]);
        Socialite::fake('google', $this->googleUser(['email' => 'different@example.test']));

        $this->get(route('portal.oauth.google.callback'))->assertRedirect(route('portal.dashboard', absolute: false));
        $this->assertAuthenticatedAs($first, 'portal');
        $this->assertSame(1, PortalSocialAccount::where('provider_user_id', 'google-123')->count());
        $this->assertDatabaseMissing('portal_users', ['email' => 'different@example.test']);
    }

    private function googleUser(array $overrides = []): SocialiteUser
    {
        return SocialiteUser::fake([...['id' => 'google-123', 'name' => 'Test User', 'email' => 'owner@example.test', 'avatar' => 'https://example.test/avatar.png', 'verified_email' => true], ...$overrides]);
    }
}
