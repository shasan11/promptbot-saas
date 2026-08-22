<?php

namespace Tests\Feature\Tenant\Inbox;

use App\Enums\Inbox\ControlState;
use App\Models\AI\ConversationInsight;
use App\Models\Channel\BotProfile;
use App\Models\Channel\Channel;
use App\Models\Channel\WebChatWidget;
use App\Models\Customer\Contact;
use App\Models\Customer\CustomerActivity;
use App\Models\Inbox\Conversation;
use App\Models\Team;
use App\Services\AI\AutonomousReplyService;
use App\Services\Channels\WebChatAutoReplyService;
use App\Services\Inbox\ConversationControlService;
use App\Services\Inbox\ConversationReplyOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Covers who owns replying to a conversation: the transitions themselves
 * (`ConversationControlService`) and the gate order the orchestrator applies
 * before spending anything on generation.
 *
 * Runs against a real tenant database rather than mocks because the two
 * properties that matter most — escalation being idempotent, and routing not
 * yanking an owned conversation away — are properties of persisted state.
 *
 * The reply *engines* are the exception and are mocked: whether the AI can
 * produce an answer is a separate concern from whether it is allowed to try,
 * and this class is about the latter.
 */
class ConversationControlTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        $this->cleanUpTenants();
        parent::tearDown();
    }

    /**
     * A conversation in the state the orchestrator sees on a normal inbound
     * message: web chat, AI in control, no failures recorded.
     */
    private function makeConversation(string $channelType = 'web_chat', array $attributes = []): Conversation
    {
        $contact = Contact::create([
            'first_name' => 'Test', 'last_name' => 'Visitor', 'display_name' => 'Test Visitor',
            'email' => 'visitor+'.Str::random(6).'@example.test', 'status' => 'active', 'source' => 'web_chat',
        ]);

        $channel = Channel::create([
            'type' => $channelType, 'name' => Str::title($channelType).' Channel', 'status' => 'active', 'auto_reply_enabled' => true,
        ]);

        if ($channelType === 'web_chat') {
            WebChatWidget::create(['channel_id' => $channel->id, 'public_key' => Str::random(48), 'widget_name' => 'Support']);
        }

        return Conversation::create(array_merge([
            'contact_id' => $contact->id,
            'channel_id' => $channel->id,
            'status' => 'open',
            'first_message_at' => now(),
            'last_message_at' => now(),
        ], $attributes));
    }

    private function control(): ConversationControlService
    {
        return app(ConversationControlService::class);
    }

    private function escalationActivityCount(Conversation $conversation): int
    {
        return CustomerActivity::where('event_type', 'conversation.escalated')
            ->where('related_id', $conversation->id)
            ->count();
    }

    /**
     * Repeated escalations are the normal case, not an edge case: three
     * unanswerable questions in a row and the customer then typing "agent"
     * both escalate the same thread. The second one must be a no-op — not a
     * re-route, not a second timeline entry, not a second notification.
     */
    public function test_escalation_is_idempotent_and_leaves_an_owned_conversation_with_its_owner(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $owner = $this->createTenantUser($tenant, ['name' => 'Existing Owner']);

        tenancy()->initialize($tenant);

        $conversation = $this->makeConversation();
        $escalationTeam = Team::create(['name' => 'Escalations', 'slug' => 'escalations-'.Str::random(4)]);

        $this->assertTrue($this->control()->escalate($conversation, 'customer_requested_human', teamId: $escalationTeam->id));

        $conversation->refresh();
        $this->assertSame(ControlState::PendingHuman, $conversation->control_state);
        $this->assertNotNull($conversation->control_changed_at);
        $this->assertSame($escalationTeam->id, $conversation->team_id);
        $this->assertSame(1, $this->escalationActivityCount($conversation));

        $firstChangedAt = $conversation->control_changed_at;

        // Second escalation, different reason — still already queued.
        $this->assertFalse($this->control()->escalate($conversation, 'repeated_no_answer'));

        $conversation->refresh();
        $this->assertSame(ControlState::PendingHuman, $conversation->control_state);
        $this->assertEquals($firstChangedAt->timestamp, $conversation->control_changed_at->timestamp);
        $this->assertSame(1, $this->escalationActivityCount($conversation), 'The repeat escalation wrote a second timeline entry.');

        // An escalation must never take a conversation away from the agent
        // already working it, even when a team is explicitly named.
        $owned = $this->makeConversation(attributes: ['assignee_id' => $owner->id]);
        $this->assertTrue($this->control()->escalate($owned, 'risk_flagged', teamId: $escalationTeam->id));

        $owned->refresh();
        $this->assertSame($owner->id, $owned->assignee_id);
        $this->assertNull($owned->team_id, 'Escalation re-routed a conversation that already had an owner.');

        // Escalated work has to be visible: a resolved thread that just got
        // escalated is, by definition, not resolved.
        $resolved = $this->makeConversation(attributes: ['status' => 'resolved', 'resolved_at' => now()]);
        $this->control()->escalate($resolved, 'customer_requested_human');
        $this->assertSame('open', $resolved->refresh()->status);

        tenancy()->end();
    }

    public function test_a_human_takeover_permanently_stops_automated_replies(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $agent = $this->createTenantUser($tenant, ['name' => 'Dana Agent']);

        tenancy()->initialize($tenant);

        $conversation = $this->makeConversation(attributes: ['ai_failure_count' => 3]);

        $this->control()->humanTookOver($conversation, $agent);

        $conversation->refresh();
        $this->assertSame(ControlState::Human, $conversation->control_state);
        $this->assertFalse($conversation->control_state->allowsAutomatedReply());
        // A human answering ends the run of AI failures the counter describes.
        $this->assertSame(0, $conversation->ai_failure_count);
        $this->assertSame(1, CustomerActivity::where('event_type', 'conversation.human_takeover')->where('related_id', $conversation->id)->count());

        // Idempotent — a teammate sending five replies is one takeover.
        $this->control()->humanTookOver($conversation, $agent);
        $this->assertSame(1, CustomerActivity::where('event_type', 'conversation.human_takeover')->where('related_id', $conversation->id)->count());

        // Handing back is legal from `human` (a resolved thread starting
        // fresh) but never from `pending_human`, where a person is still
        // expected to act and the AI stealing it back would strand them.
        $this->control()->returnToAi($conversation);
        $this->assertSame(ControlState::Ai, $conversation->refresh()->control_state);

        $queued = $this->makeConversation();
        $this->control()->escalate($queued, 'customer_requested_human');
        $this->control()->returnToAi($queued);
        $this->assertSame(ControlState::PendingHuman, $queued->refresh()->control_state);

        tenancy()->end();
    }

    public function test_ai_failures_escalate_exactly_at_the_threshold_and_can_be_disabled(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        $conversation = $this->makeConversation();

        $this->assertFalse($this->control()->recordAiFailure($conversation, threshold: 2));
        $this->assertSame(1, $conversation->refresh()->ai_failure_count);
        $this->assertSame(ControlState::Ai, $conversation->control_state);

        $this->assertTrue($this->control()->recordAiFailure($conversation, threshold: 2));
        $this->assertSame(ControlState::PendingHuman, $conversation->refresh()->control_state);

        // Past the threshold the counter keeps rising but nothing re-escalates.
        $this->assertFalse($this->control()->recordAiFailure($conversation, threshold: 2));
        $this->assertSame(3, $conversation->refresh()->ai_failure_count);
        $this->assertSame(1, $this->escalationActivityCount($conversation));

        // A threshold of 0 means the tenant turned failure-escalation off. It
        // must not be read as "escalate on the very first failure".
        $tolerant = $this->makeConversation();
        $this->assertFalse($this->control()->recordAiFailure($tolerant, threshold: 0));
        $this->assertFalse($this->control()->recordAiFailure($tolerant, threshold: 0));
        $this->assertSame(ControlState::Ai, $tolerant->refresh()->control_state);
        $this->assertSame(2, $tolerant->ai_failure_count);

        tenancy()->end();
    }

    /**
     * The gate order is the whole point of the orchestrator: an explicit
     * request for a person is honoured before any generation cost is incurred,
     * a conversation a human owns is never answered automatically, and risk
     * escalation happens before an engine is chosen.
     */
    public function test_the_orchestrator_applies_its_gates_in_order(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        // 1. Human request wins — before an engine is even consulted.
        $autonomous = Mockery::mock(AutonomousReplyService::class);
        $autonomous->shouldNotReceive('eligibleAgent');
        $widget = Mockery::mock(WebChatAutoReplyService::class);
        $widget->shouldNotReceive('reply');

        $requested = $this->makeConversation();
        $this->orchestrator($autonomous, $widget)->handleInbound($requested, 'can I speak to a human please');

        $requested->refresh();
        $this->assertSame(ControlState::PendingHuman, $requested->control_state);
        $this->assertSame(1, $this->escalationActivityCount($requested));

        // The matcher is deliberately conservative; these must not trigger it.
        foreach (['is this a human being answering me out of curiosity', 'my agent booked the flight'] as $innocuous) {
            $ordinary = $this->makeConversation();
            $engine = Mockery::mock(WebChatAutoReplyService::class);
            $engine->shouldReceive('reply')->once()->andReturn(true);
            $noAgent = Mockery::mock(AutonomousReplyService::class);
            $noAgent->shouldReceive('eligibleAgent')->andReturn(null);

            $this->orchestrator($noAgent, $engine)->handleInbound($ordinary, $innocuous);
            $this->assertSame(ControlState::Ai, $ordinary->refresh()->control_state, "\"{$innocuous}\" was treated as a handoff request.");
        }

        // 2. Control gate — a conversation a human owns gets no engine at all,
        //    and no failure is recorded against the AI for not answering it.
        $ownedAutonomous = Mockery::mock(AutonomousReplyService::class);
        $ownedAutonomous->shouldNotReceive('eligibleAgent');
        $ownedWidget = Mockery::mock(WebChatAutoReplyService::class);
        $ownedWidget->shouldNotReceive('reply');

        $owned = $this->makeConversation(attributes: ['control_state' => ControlState::Human]);
        $this->orchestrator($ownedAutonomous, $ownedWidget)->handleInbound($owned, 'is my refund processed yet');

        $owned->refresh();
        $this->assertSame(ControlState::Human, $owned->control_state);
        $this->assertSame(0, $owned->ai_failure_count);

        // 3. Risk gate — runs before engine selection, so a flagged
        //    conversation escalates instead of being answered.
        $riskyAutonomous = Mockery::mock(AutonomousReplyService::class);
        $riskyAutonomous->shouldNotReceive('eligibleAgent');
        $riskyWidget = Mockery::mock(WebChatAutoReplyService::class);
        $riskyWidget->shouldNotReceive('reply');

        $risky = $this->makeConversation();
        ConversationInsight::create([
            'conversation_id' => $risky->id, 'sentiment' => 'negative', 'urgency' => 'high',
            'risk_flags' => ['chargeback_threat'], 'classified_at' => now(),
        ]);

        $this->orchestrator($riskyAutonomous, $riskyWidget)->handleInbound($risky, 'this is the third time I am asking about my order');

        $risky->refresh();
        $this->assertSame(ControlState::PendingHuman, $risky->control_state);

        tenancy()->end();
    }

    /**
     * A reply that did not happen is a failure the customer can feel. Counting
     * it is what makes "hand off after N unanswerable questions" work rather
     * than letting the bot fail silently forever.
     */
    public function test_an_engine_that_cannot_answer_is_counted_as_an_ai_failure(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        $conversation = $this->makeConversation();

        $autonomous = Mockery::mock(AutonomousReplyService::class);
        $autonomous->shouldReceive('eligibleAgent')->andReturn(null);
        $widget = Mockery::mock(WebChatAutoReplyService::class);
        $widget->shouldReceive('reply')->twice()->andReturn(false);

        $orchestrator = $this->orchestrator($autonomous, $widget);
        $orchestrator->handleInbound($conversation, 'what is your VAT number');
        $this->assertSame(1, $conversation->refresh()->ai_failure_count);
        $this->assertSame(ControlState::Ai, $conversation->control_state);

        // The default profile tolerates two failures.
        $orchestrator->handleInbound($conversation, 'and your company registration number');
        $conversation->refresh();
        $this->assertSame(2, $conversation->ai_failure_count);
        $this->assertSame(ControlState::PendingHuman, $conversation->control_state);

        // A channel with no engine configured at all is a different case: the
        // conversation was always destined for a human, so there is no AI
        // failure to record against it.
        $engineless = $this->makeConversation('email');
        $noEngine = Mockery::mock(AutonomousReplyService::class);
        $noEngine->shouldReceive('eligibleAgent')->andReturn(null);
        $unusedWidget = Mockery::mock(WebChatAutoReplyService::class);
        $unusedWidget->shouldNotReceive('reply');

        $this->orchestrator($noEngine, $unusedWidget)->handleInbound($engineless, 'hello, is anyone there');

        $engineless->refresh();
        $this->assertSame(0, $engineless->ai_failure_count);
        $this->assertSame(ControlState::Ai, $engineless->control_state);

        tenancy()->end();
    }

    /**
     * `effectiveBotProfile()` never returns null, and an attached profile's
     * settings really do replace the previously hardcoded constants.
     */
    public function test_the_attached_bot_profile_drives_escalation_behaviour(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        $conversation = $this->makeConversation();
        $this->assertInstanceOf(BotProfile::class, $conversation->channel->effectiveBotProfile());

        $profile = BotProfile::create([
            'name' => 'Never hands off', 'escalate_on_request' => false,
            'escalate_after_failures' => 0, 'escalate_on_negative_sentiment' => false, 'escalate_on_risk_flags' => false,
        ]);
        $conversation->channel->forceFill(['bot_profile_id' => $profile->id])->save();
        $conversation->load('channel.botProfile');

        $this->assertSame($profile->id, $conversation->channel->effectiveBotProfile()->id);

        $autonomous = Mockery::mock(AutonomousReplyService::class);
        $autonomous->shouldReceive('eligibleAgent')->andReturn(null);
        $widget = Mockery::mock(WebChatAutoReplyService::class);
        // With escalate_on_request off, an explicit handoff request falls
        // through to the engine instead of escalating.
        $widget->shouldReceive('reply')->once()->andReturn(false);

        $this->orchestrator($autonomous, $widget)->handleInbound($conversation, 'get me to a human');

        $conversation->refresh();
        $this->assertSame(ControlState::Ai, $conversation->control_state);
        $this->assertSame(1, $conversation->ai_failure_count);

        tenancy()->end();
    }

    private function orchestrator(AutonomousReplyService $autonomous, WebChatAutoReplyService $widget): ConversationReplyOrchestrator
    {
        return new ConversationReplyOrchestrator($this->control(), $autonomous, $widget);
    }
}
