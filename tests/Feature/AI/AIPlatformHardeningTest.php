<?php

namespace Tests\Feature\AI;

use App\Enums\AI\AgentStatus;
use App\Enums\AI\ApprovalStatus;
use App\Enums\AI\DeploymentMode;
use App\Models\AI\Agent;
use App\Models\AI\Run;
use App\Models\Channel\Channel;
use App\Models\Connections\ConnectionAction;
use App\Models\Customer\Contact;
use App\Models\Feature;
use App\Models\Inbox\Conversation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\AI\AIToolExecutionService;
use App\Services\AI\AutonomousReplyService;
use App\Services\AI\NeuronToolFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Covers the tenant AI platform's non-negotiable safety guarantees end to
 * end, against real tenant databases (mirroring TenantIsolationTest's
 * pattern) rather than mocking the tenancy boundary — these are exactly the
 * properties a mock could accidentally paper over.
 */
class AIPlatformHardeningTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    /** Grants a tenant an active subscription to a plan with ai_platform enabled, satisfying the `tenant.feature:ai_platform` route gate. */
    private function grantAiPlatformFeature(Tenant $tenant): void
    {
        $plan = Plan::create(['name' => 'Test Plan '.$tenant->id, 'slug' => 'test-plan-'.$tenant->id, 'monthly_price' => 0, 'annual_price' => 0, 'currency' => 'USD', 'trial_days' => 0, 'is_active' => true, 'is_public' => false, 'sort_order' => 1]);
        $feature = Feature::firstOrCreate(['code' => 'ai_platform'], ['name' => 'AI Platform', 'type' => 'boolean', 'description' => 'Tenant AI agents and copilot']);
        $plan->features()->attach($feature->id, ['enabled' => true, 'unlimited' => true]);
        Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_interval' => 'monthly', 'current_period_starts_at' => now(), 'current_period_ends_at' => now()->addMonth()]);
    }

    public function test_tenant_a_cannot_see_tenant_b_ai_agents(): void
    {
        [$tenantA, $domainA] = $this->createTenantWithDomain();
        [$tenantB] = $this->createTenantWithDomain();
        $this->grantAiPlatformFeature($tenantA);
        $ownerA = $this->createTenantUser($tenantA, ['name' => 'Owner A'], 'Tenant Owner');
        $this->createTenantUser($tenantB, ['name' => 'Owner B'], 'Tenant Owner');

        tenancy()->initialize($tenantA);
        Agent::create(['agent_key' => 'agent-a', 'name' => 'Agent A', 'status' => AgentStatus::Draft, 'deployment_mode' => DeploymentMode::Copilot]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        Agent::create(['agent_key' => 'agent-b', 'name' => 'Agent B', 'status' => AgentStatus::Draft, 'deployment_mode' => DeploymentMode::Copilot]);
        tenancy()->end();

        $response = $this->actingAs($ownerA, 'tenant')->get("http://{$domainA}/ai/agents");
        $response->assertOk();

        $names = collect($response->viewData('page')['props']['agents'])->pluck('name')->all();
        $this->assertContains('Agent A', $names);
        $this->assertNotContains('Agent B', $names);
    }

    public function test_user_without_copilot_permission_cannot_run_inbox_ai(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $this->grantAiPlatformFeature($tenant);
        $viewer = $this->createTenantUser($tenant, ['name' => 'Viewer'], 'Viewer');

        tenancy()->initialize($tenant);
        $channel = Channel::create(['type' => 'web_chat', 'name' => 'Widget', 'status' => 'active']);
        $contact = Contact::create(['display_name' => 'Jane', 'status' => 'active', 'source' => 'web_chat']);
        $conversation = Conversation::create(['contact_id' => $contact->id, 'channel_id' => $channel->id, 'status' => 'open', 'priority' => 'normal']);
        $conversationUuid = $conversation->public_uuid;
        tenancy()->end();

        $response = $this->actingAs($viewer, 'tenant')->post("http://{$domain}/inbox/{$conversationUuid}/ai", ['operation' => 'summary']);
        $response->assertForbidden();
    }

    public function test_user_without_provider_permission_cannot_manage_providers(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $this->grantAiPlatformFeature($tenant);
        $agentRole = $this->createTenantUser($tenant, ['name' => 'Support Agent'], 'Agent');

        $response = $this->actingAs($agentRole, 'tenant')->post("http://{$domain}/ai/providers", [
            'name' => 'Sneaky Provider', 'provider' => 'openai', 'api_key' => 'sk-test',
        ]);
        $response->assertForbidden();
    }

    public function test_unassigned_connection_action_is_not_exposed_as_a_tool(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        tenancy()->initialize($tenant);

        $agent = Agent::create(['agent_key' => 'copilot', 'name' => 'Copilot', 'status' => AgentStatus::Active, 'deployment_mode' => DeploymentMode::Copilot]);
        ConnectionAction::create(['tenant_id' => $tenant->id, 'key' => 'lookup_order', 'name' => 'Lookup Order', 'risk_level' => 'low', 'enabled_for_ai' => true, 'status' => 'active']);
        // Deliberately not attached to the agent via ai_agent_tools.
        $run = Run::create(['feature' => 'test', 'operation' => 'test', 'agent_id' => $agent->id]);

        $tools = app(NeuronToolFactory::class)->forAgent($agent, $run, null);

        $this->assertCount(0, $tools);
        tenancy()->end();
    }

    public function test_high_risk_action_requires_approval_instead_of_executing(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        tenancy()->initialize($tenant);

        $agent = Agent::create(['agent_key' => 'copilot', 'name' => 'Copilot', 'status' => AgentStatus::Active, 'deployment_mode' => DeploymentMode::Copilot, 'human_approval_mode' => 'risk_based']);
        $action = ConnectionAction::create(['tenant_id' => $tenant->id, 'key' => 'delete_account', 'name' => 'Delete Account', 'risk_level' => 'high', 'enabled_for_ai' => true, 'status' => 'active']);
        $agent->connectionActions()->attach($action->id, ['enabled' => true]);
        $run = Run::create(['feature' => 'test', 'operation' => 'test', 'agent_id' => $agent->id]);

        $result = app(AIToolExecutionService::class)->invoke($run, $agent, $action, ['id' => '123'], null);

        $this->assertStringContainsString('approval', strtolower($result));
        $this->assertStringNotContainsString('done', strtolower($result));
        $this->assertDatabaseHas('ai_approval_requests', ['ai_run_id' => $run->id, 'status' => ApprovalStatus::Pending->value]);
        $this->assertDatabaseMissing('ai_tool_calls', ['ai_run_id' => $run->id, 'status' => 'completed']);
        tenancy()->end();
    }

    public function test_duplicate_low_risk_tool_call_does_not_execute_twice(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        tenancy()->initialize($tenant);

        $agent = Agent::create(['agent_key' => 'copilot', 'name' => 'Copilot', 'status' => AgentStatus::Active, 'deployment_mode' => DeploymentMode::Copilot]);
        // Low risk, but with no real Connection behind it the execution call
        // fails safely — which is fine for this test: we only assert the
        // SECOND call short-circuits on the idempotency hash rather than
        // reaching execution again, not that execution itself succeeds.
        $action = ConnectionAction::create(['tenant_id' => $tenant->id, 'key' => 'search_records', 'name' => 'Search Records', 'risk_level' => 'low', 'enabled_for_ai' => true, 'status' => 'active']);
        $agent->connectionActions()->attach($action->id, ['enabled' => true]);
        $run = Run::create(['feature' => 'test', 'operation' => 'test', 'agent_id' => $agent->id]);

        app(AIToolExecutionService::class)->invoke($run, $agent, $action, ['query' => 'abc'], null);
        $firstCount = \App\Models\AI\ToolCall::query()->where('ai_run_id', $run->id)->count();

        app(AIToolExecutionService::class)->invoke($run, $agent, $action, ['query' => 'abc'], null);
        $secondCount = \App\Models\AI\ToolCall::query()->where('ai_run_id', $run->id)->count();

        $this->assertSame(1, $firstCount);
        $this->assertSame(1, $secondCount, 'A duplicate identical tool call must not create a second ToolCall row.');
        tenancy()->end();
    }

    public function test_autonomous_replies_are_disabled_by_default(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        tenancy()->initialize($tenant);

        $channel = Channel::create(['type' => 'web_chat', 'name' => 'Widget', 'status' => 'active', 'auto_reply_enabled' => true]);
        $agent = Agent::create(['agent_key' => 'autonomous', 'name' => 'Autonomous', 'status' => AgentStatus::Active, 'deployment_mode' => DeploymentMode::Autonomous, 'auto_reply_enabled' => true]);
        $agent->channels()->attach($channel->id, ['deployment_mode' => 'autonomous', 'enabled' => true]);
        $contact = Contact::create(['display_name' => 'Jane', 'status' => 'active', 'source' => 'web_chat']);
        $conversation = Conversation::create(['contact_id' => $contact->id, 'channel_id' => $channel->id, 'status' => 'open', 'priority' => 'normal']);

        // Even with an Agent fully configured for autonomy on this channel,
        // the tenant-level `ai.autonomous_replies_enabled` setting and the
        // `ai_autonomous_replies` plan feature both default OFF, so no
        // eligible agent should be returned.
        $eligible = app(AutonomousReplyService::class)->eligibleAgent($conversation);

        $this->assertNull($eligible);
        tenancy()->end();
    }
}
