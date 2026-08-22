<?php

namespace Tests\Feature\Tenant\Reporting;

use App\Enums\Inbox\ControlState;
use App\Models\Channel\Channel;
use App\Models\Customer\Contact;
use App\Models\Inbox\Conversation;
use App\Services\Analytics\BotPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Exercises the bot analytics against a hand-built dataset with known answers.
 *
 * Metrics are the easiest thing in a product to ship wrong and never notice —
 * a plausible number is indistinguishable from a correct one on a dashboard.
 * Every figure below is checked against a scenario small enough to count by
 * hand, and the SQL runs on real MySQL because most of it (JSON extraction,
 * correlated subqueries, TIMESTAMPDIFF) has no meaning on any other driver.
 */
class BotPerformanceTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    public function test_it_reports_the_bot_metrics_for_a_known_dataset(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        $channel = Channel::create(['type' => 'web_chat', 'name' => 'Site Chat', 'status' => 'active']);
        $askedAt = now()->subDays(2)->startOfHour();

        // A — the bot answered twice and no teammate ever replied. Deflected.
        $deflected = $this->conversation($channel, $askedAt);
        $this->inbound($deflected, $askedAt);
        $this->aiReply($deflected, $askedAt->copy()->addSeconds(30));
        $this->inbound($deflected, $askedAt->copy()->addMinutes(5));
        $this->aiReply($deflected, $askedAt->copy()->addMinutes(5)->addSeconds(90));
        $this->rate($deflected, 5, $askedAt);

        // B — the bot answered, then a teammate took over. Not deflected.
        $takenOver = $this->conversation($channel, $askedAt, ControlState::Human);
        $this->inbound($takenOver, $askedAt);
        $this->aiReply($takenOver, $askedAt->copy()->addSeconds(10));
        $this->humanReply($takenOver, $askedAt->copy()->addMinutes(3));
        $this->rate($takenOver, 2, $askedAt);

        // C — escalated before anyone answered, and the bot said it did not
        // know. Neither deflected nor answered.
        $escalated = $this->conversation($channel, $askedAt, ControlState::PendingHuman);
        $this->inbound($escalated, $askedAt);
        $this->noAnswerReply($escalated, $askedAt->copy()->addSeconds(20));
        $this->escalation($escalated, 'customer_requested_human', $askedAt);
        $this->escalation($this->conversation($channel, $askedAt, ControlState::PendingHuman), 'repeated_no_answer', $askedAt);

        $this->retrievalLog('widget', zeroResults: false, at: $askedAt);
        $this->retrievalLog('widget', zeroResults: true, at: $askedAt);
        $this->retrievalLog('agent', zeroResults: false, at: $askedAt);
        // Staff experimenting in the playground is not a customer question and
        // must not count against the answer rate.
        $this->retrievalLog('playground', zeroResults: true, at: $askedAt);

        DB::table('ai_runs')->insert([
            'public_uuid' => (string) Str::uuid(), 'feature' => 'inbox_copilot', 'operation' => 'draft',
            'conversation_id' => $deflected->id, 'status' => 'succeeded', 'latency_ms' => 900,
            'total_token_count' => 1200, 'estimated_cost' => 0.02,
            'created_at' => $askedAt, 'updated_at' => $askedAt,
        ]);

        DB::table('knowledge_gaps')->insert([
            'uuid' => (string) Str::uuid(), 'question' => 'Do you ship to Norway?', 'query_hash' => hash('sha256', 'norway'),
            'dedupe_key' => hash('sha256', 'gap-norway'), 'origin' => 'zero_result', 'occurrences' => 7,
            'status' => 'open', 'first_seen_at' => $askedAt, 'last_seen_at' => $askedAt,
            'created_at' => $askedAt, 'updated_at' => $askedAt,
        ]);

        $metrics = app(BotPerformanceService::class)->summary(now()->subDays(30)->startOfDay(), now()->endOfDay());

        // Four conversations: two the bot spoke in and one of those a human
        // also answered, one escalated with a "don't know", one silent.
        $this->assertSame(4, $metrics['volume']['total']);
        $this->assertSame(3, $metrics['volume']['answered_by_ai']);
        $this->assertSame(1, $metrics['volume']['human_replied']);
        $this->assertSame(3, $metrics['volume']['handed_off']);
        $this->assertSame(1, $metrics['volume']['untouched']);

        // Deflected = answered by the bot and by nobody else: A only.
        $this->assertSame(1, $metrics['deflection']['count']);
        $this->assertSame(25.0, $metrics['deflection']['rate']);
        $this->assertSame(75.0, $metrics['handoff']['rate']);

        $reasons = collect($metrics['handoff']['reasons'])->pluck('total', 'reason')->all();
        $this->assertSame(1, $reasons['customer_requested_human'] ?? null);
        $this->assertSame(1, $reasons['repeated_no_answer'] ?? null);

        // Three customer-facing retrieval attempts, one of which found nothing.
        // The playground row is excluded.
        $this->assertSame(3, $metrics['no_answer']['questions']);
        $this->assertSame(1, $metrics['no_answer']['unanswered']);
        $this->assertSame(33.3, $metrics['no_answer']['rate']);
        $this->assertSame(1, $metrics['no_answer']['conversations_affected']);

        $this->assertSame(2, $metrics['csat']['responses']);
        $this->assertSame(3.5, $metrics['csat']['average']);
        $this->assertSame(50.0, $metrics['csat']['positive_rate']);

        // Waits of 10s, 20s, 30s and 90s, each measured from the customer's
        // own preceding message rather than from the start of the thread.
        $this->assertSame(4, $metrics['latency']['samples']);
        $this->assertSame(20, $metrics['latency']['p50_seconds']);
        $this->assertSame(90, $metrics['latency']['p95_seconds']);

        $this->assertSame(0.02, $metrics['cost']['total']);
        $this->assertSame(0.005, $metrics['cost']['per_conversation']);
        $this->assertSame(1, $metrics['cost']['attributed_conversations']);

        $this->assertSame('Do you ship to Norway?', $metrics['gaps'][0]['question']);
        $this->assertSame(7, $metrics['gaps'][0]['occurrences']);

        tenancy()->end();
    }

    /**
     * A conversation created before the reporting window opened must not be
     * counted, even when the bot answered it inside the window — otherwise
     * every rate is wrong at the range boundary.
     */
    public function test_conversations_are_counted_in_the_period_they_started(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        $channel = Channel::create(['type' => 'web_chat', 'name' => 'Site Chat', 'status' => 'active']);

        $old = $this->conversation($channel, now()->subDays(60));
        $this->inbound($old, now()->subDays(60));
        $this->aiReply($old, now()->subDay());

        $metrics = app(BotPerformanceService::class)->summary(now()->subDays(30)->startOfDay(), now()->endOfDay());

        $this->assertSame(0, $metrics['volume']['total']);
        $this->assertNull($metrics['deflection']['rate']);
        $this->assertNull($metrics['csat']['average']);
        $this->assertNull($metrics['cost']['per_conversation']);

        tenancy()->end();
    }

    private function conversation(Channel $channel, $createdAt, ControlState $state = ControlState::Ai): Conversation
    {
        $contact = Contact::create([
            'display_name' => 'Visitor '.Str::random(5), 'email' => 'v'.Str::random(8).'@example.test',
            'status' => 'active', 'source' => 'web_chat',
        ]);

        $conversation = Conversation::create([
            'contact_id' => $contact->id, 'channel_id' => $channel->id, 'status' => 'open',
            'control_state' => $state, 'first_message_at' => $createdAt, 'last_message_at' => $createdAt,
        ]);

        // created_at drives which reporting period the conversation belongs to.
        $conversation->forceFill(['created_at' => $createdAt])->save();

        return $conversation;
    }

    private function inbound(Conversation $conversation, $at): void
    {
        $this->message($conversation, 'contact', 'inbound', 'A question', $at);
    }

    private function aiReply(Conversation $conversation, $at): void
    {
        $this->message($conversation, 'ai', 'outbound', 'An answer', $at, ['ai_generated' => true, 'generated_by' => 'ai']);
    }

    private function noAnswerReply(Conversation $conversation, $at): void
    {
        $this->message($conversation, 'ai', 'outbound', 'I could not find an answer.', $at, ['ai_generated' => true, 'generated_by' => 'no_answer']);
    }

    private function humanReply(Conversation $conversation, $at): void
    {
        $this->message($conversation, 'user', 'outbound', 'A teammate here.', $at);
    }

    private function message(Conversation $conversation, string $senderType, string $direction, string $body, $at, array $metadata = []): void
    {
        DB::table('messages')->insert([
            'public_uuid' => (string) Str::uuid(), 'conversation_id' => $conversation->id,
            'sender_type' => $senderType, 'direction' => $direction, 'message_type' => 'text',
            'body' => $body, 'status' => 'delivered', 'sent_at' => $at,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function rate(Conversation $conversation, int $score, $at): void
    {
        DB::table('conversation_ratings')->insert([
            'conversation_id' => $conversation->id, 'score' => $score,
            'rated_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function escalation(Conversation $conversation, string $reason, $at): void
    {
        DB::table('customer_activities')->insert([
            'public_uuid' => (string) Str::uuid(), 'event_type' => 'conversation.escalated',
            'description' => 'Escalated: '.$reason, 'related_type' => Conversation::class, 'related_id' => $conversation->id,
            'metadata' => json_encode(['reason' => $reason]), 'occurred_at' => $at,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function retrievalLog(string $channel, bool $zeroResults, $at): void
    {
        DB::table('knowledge_retrieval_logs')->insert([
            'uuid' => (string) Str::uuid(), 'channel' => $channel, 'query' => 'a question',
            'query_hash' => hash('sha256', 'a question'.Str::random(4)),
            'results_returned' => $zeroResults ? 0 : 3, 'zero_results' => $zeroResults,
            'below_threshold' => false, 'total_ms' => 120,
            'created_at' => $at,
        ]);
    }
}
