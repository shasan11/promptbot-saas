<?php

namespace Tests\Feature\Tenant\Channels;

use App\Models\Channel\BotProfile;
use App\Models\Channel\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * The bot profile admin screens. `bot_profiles` shipped with no way to reach
 * it, so every workspace ran on `BotProfile::defaults()` whether or not that
 * suited them.
 */
class BotProfileAdminTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    public function test_an_administrator_can_manage_bot_profiles_end_to_end(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Channel Admin'], 'Tenant Administrator');

        $payload = [
            'name' => 'Careful bot', 'tone' => 'friendly', 'response_length' => 'short',
            'language_policy' => 'match_customer', 'default_language' => 'en',
            'escalate_on_request' => true, 'escalate_after_failures' => 1,
            'escalate_on_negative_sentiment' => true, 'escalate_on_risk_flags' => true,
            'escalation_team_id' => null, 'is_default' => true,
        ];

        $this->actingAs($admin, 'tenant')->get("http://{$domain}/bot-profiles")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Admin/BotProfiles/Index')->has('profiles.data', 0));

        $this->actingAs($admin, 'tenant')->post("http://{$domain}/bot-profiles", $payload)
            ->assertRedirect("http://{$domain}/bot-profiles");

        tenancy()->initialize($tenant);
        $profile = BotProfile::firstOrFail();
        $this->assertSame('friendly', $profile->tone);
        $this->assertSame(1, $profile->escalate_after_failures);
        $this->assertTrue($profile->is_default);
        tenancy()->end();

        // Editing loads the profile with the channel count the delete warning
        // depends on.
        $this->actingAs($admin, 'tenant')->get("http://{$domain}/bot-profiles/{$profile->public_uuid}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Admin/BotProfiles/Form')->where('profile.name', 'Careful bot')->has('profile.channels_count'));

        $this->actingAs($admin, 'tenant')->put(
            "http://{$domain}/bot-profiles/{$profile->public_uuid}",
            array_merge($payload, ['name' => 'Patient bot', 'escalate_after_failures' => 4]),
        )->assertRedirect();

        tenancy()->initialize($tenant);
        $profile->refresh();
        $this->assertSame('Patient bot', $profile->name);
        $this->assertSame(4, $profile->escalate_after_failures);
        tenancy()->end();

        // Exactly one default. Nominating a second must demote the first, or
        // `workspaceDefault()` becomes "whichever row the database returns".
        $this->actingAs($admin, 'tenant')->post("http://{$domain}/bot-profiles", array_merge($payload, ['name' => 'Terse bot']))
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertSame(1, BotProfile::where('is_default', true)->count());
        $this->assertSame('Terse bot', BotProfile::where('is_default', true)->value('name'));

        // A channel with no profile attached now resolves to the workspace
        // default rather than the built-in one.
        $channel = Channel::create(['type' => 'web_chat', 'name' => 'Site Chat', 'status' => 'active']);
        $this->assertSame('Terse bot', $channel->effectiveBotProfile()->name);

        $channel->forceFill(['bot_profile_id' => $profile->id])->save();
        $this->assertSame('Patient bot', $channel->fresh()->effectiveBotProfile()->name);
        tenancy()->end();

        // Deleting detaches rather than cascading — the channel keeps working.
        $this->actingAs($admin, 'tenant')->delete("http://{$domain}/bot-profiles/{$profile->public_uuid}")
            ->assertRedirect()
            ->assertSessionHas('status', fn ($status) => str_contains($status, '1 channel'));

        tenancy()->initialize($tenant);
        $this->assertNull(BotProfile::find($profile->id));
        $channel->refresh();
        $this->assertNull($channel->bot_profile_id);
        $this->assertSame('Terse bot', $channel->effectiveBotProfile()->name);
        tenancy()->end();
    }

    public function test_the_channel_form_offers_the_profiles_and_persists_the_choice(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Channel Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);
        $profile = BotProfile::create(['name' => 'Formal bot', 'tone' => 'professional', 'response_length' => 'detailed']);
        tenancy()->end();

        $this->actingAs($admin, 'tenant')->get("http://{$domain}/channels/create?type=web_chat")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Admin/Channels/Form')->has('botProfiles', 1)->where('botProfiles.0.name', 'Formal bot'));

        $this->actingAs($admin, 'tenant')->post("http://{$domain}/channels", [
            'type' => 'web_chat', 'name' => 'Main Website Chat', 'status' => 'active',
            'team_id' => null, 'default_assignee_id' => null, 'business_hours_policy_id' => null,
            'bot_profile_id' => $profile->id, 'auto_reply_enabled' => false, 'signature' => '',
            'widget' => [
                'widget_name' => 'Website Support', 'primary_color' => '#2563eb', 'launcher_position' => 'right',
                'welcome_message' => 'Welcome', 'offline_message' => 'Offline', 'supported_languages' => ['en'],
                'allowed_origins' => [], 'privacy_url' => null, 'terms_url' => null,
                'allow_attachments' => true, 'require_email' => true, 'require_name' => true,
            ],
        ])->assertRedirect();

        tenancy()->initialize($tenant);
        $channel = Channel::where('type', 'web_chat')->firstOrFail();
        $this->assertSame($profile->id, $channel->bot_profile_id);
        $this->assertSame('Formal bot', $channel->effectiveBotProfile()->name);
        tenancy()->end();
    }

    public function test_a_user_without_channel_permissions_cannot_reach_the_screens(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $agent = $this->createTenantUser($tenant, ['name' => 'No Access '.Str::random(4)], null);

        $this->actingAs($agent, 'tenant')->get("http://{$domain}/bot-profiles")->assertForbidden();
        $this->actingAs($agent, 'tenant')->post("http://{$domain}/bot-profiles", ['name' => 'Sneaky'])->assertForbidden();
    }
}
