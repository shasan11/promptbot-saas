<?php

namespace App\Services\Analytics;

use App\Enums\Inbox\ControlState;
use App\Models\Inbox\Conversation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Product-level analytics for the answering bot.
 *
 * Every figure here is derived from data the system already writes; nothing
 * in this class instruments anything new. Where a number could be built from
 * more than one source, the source that covers *both* reply engines was
 * chosen over the more precise one that covers only one — a deflection rate
 * that silently excludes web chat is worse than useless, because it looks
 * authoritative.
 *
 * Read the definitions as strictly as they are written. "Deflected" here does
 * not mean "the customer was satisfied"; it means no teammate had to touch
 * the conversation. That is a workload measure, and CSAT sits beside it
 * precisely so the two are read together.
 */
class BotPerformanceService
{
    /** Message sender types that represent an automated answer. */
    private const AI_SENDERS = ['ai', 'ai_agent'];

    /**
     * @return array<string, mixed>
     */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $conversations = $this->conversationCounts($from, $to);

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'volume' => $conversations,
            'deflection' => $this->deflection($conversations),
            'handoff' => [
                'rate' => $this->rate($conversations['handed_off'], $conversations['total']),
                'reasons' => $this->handoffReasons($from, $to),
            ],
            'no_answer' => $this->noAnswer($from, $to),
            'csat' => $this->csat($from, $to),
            'latency' => $this->answerLatency($from, $to),
            'cost' => $this->cost($from, $to, $conversations['total']),
            'gaps' => $this->topGaps(),
        ];
    }

    /**
     * The one query the rest of the summary is built from.
     *
     * A conversation is counted in the period it *started*, not the period a
     * message happened to land in: a thread opened on the 30th and answered on
     * the 1st belongs to one month, and splitting it across two would make
     * every rate below wrong at the boundary.
     *
     * @return array{total: int, answered_by_ai: int, human_replied: int, handed_off: int, untouched: int, deflected: int}
     */
    private function conversationCounts(CarbonInterface $from, CarbonInterface $to): array
    {
        $senders = "'".implode("','", self::AI_SENDERS)."'";

        $row = Conversation::query()
            ->whereBetween('conversations.created_at', [$from, $to])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = conversations.id AND m.direction = 'outbound' AND m.sender_type IN ({$senders}))) AS answered_by_ai")
            ->selectRaw("SUM(EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = conversations.id AND m.direction = 'outbound' AND m.sender_type = 'user')) AS human_replied")
            // Neither the bot nor a person said anything. Worth counting on
            // its own: it is the population that makes a deflection rate look
            // good for the wrong reason.
            ->selectRaw("SUM(NOT EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = conversations.id AND m.direction = 'outbound')) AS untouched")
            ->selectRaw('SUM(conversations.control_state <> ?) AS handed_off', [ControlState::Ai->value])
            // Deflected: the bot spoke, no teammate replied, and the thread is
            // not sitting in a human's queue. The control state is what makes
            // this honest — a conversation the bot answered and then escalated
            // has deflected nothing, and counting it would make the number
            // rise as the bot got worse.
            ->selectRaw("SUM(EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = conversations.id AND m.direction = 'outbound' AND m.sender_type IN ({$senders})) AND NOT EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = conversations.id AND m.direction = 'outbound' AND m.sender_type = 'user') AND conversations.control_state = ?) AS deflected", [ControlState::Ai->value])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'answered_by_ai' => (int) ($row->answered_by_ai ?? 0),
            'human_replied' => (int) ($row->human_replied ?? 0),
            'handed_off' => (int) ($row->handed_off ?? 0),
            'untouched' => (int) ($row->untouched ?? 0),
            'deflected' => (int) ($row->deflected ?? 0),
        ];
    }

    /**
     * Deflection: the bot answered and no teammate ever had to.
     *
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function deflection(array $counts): array
    {
        return [
            'count' => $counts['deflected'],
            'rate' => $this->rate($counts['deflected'], $counts['total']),
            'answered_by_ai' => $counts['answered_by_ai'],
            'human_replied' => $counts['human_replied'],
            'untouched' => $counts['untouched'],
        ];
    }

    /**
     * Why conversations were handed over, from the escalation timeline entries
     * `ConversationControlService` already writes.
     *
     * @return array<int, array{reason: string, label: string, total: int}>
     */
    private function handoffReasons(CarbonInterface $from, CarbonInterface $to): array
    {
        $labels = [
            'customer_requested_human' => 'Customer asked for a person',
            'repeated_no_answer' => 'Bot could not answer',
            'risk_flagged' => 'Risk flag raised',
            'negative_sentiment' => 'Customer was unhappy',
        ];

        return DB::table('customer_activities')
            ->where('event_type', 'conversation.escalated')
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.reason')), 'unknown') AS reason, COUNT(*) AS total")
            ->groupBy('reason')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'reason' => (string) $row->reason,
                'label' => $labels[$row->reason] ?? 'Other',
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Questions the knowledge base could not answer.
     *
     * Two views of the same failure, because they answer different questions:
     * the retrieval-log rate is per *question asked* (how often the corpus
     * comes up short), while the conversation count is per *customer affected*
     * (how many people hit it). A single frustrated person asking the same
     * thing six times moves the first a lot and the second by one.
     *
     * @return array<string, mixed>
     */
    private function noAnswer(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = DB::table('knowledge_retrieval_logs')
            ->whereBetween('created_at', [$from, $to])
            // Customer-facing surfaces only. Playground queries are staff
            // experimenting, and counting them as failures to answer a
            // customer would be simply false.
            ->whereIn('channel', ['widget', 'agent', 'inbox'])
            ->selectRaw('COUNT(*) AS total, SUM(zero_results OR below_threshold) AS unanswered')
            ->first();

        $total = (int) ($row->total ?? 0);
        $unanswered = (int) ($row->unanswered ?? 0);

        // The widget stamps its "I could not find an answer" replies, so the
        // customer-facing count needs no inference.
        $affected = DB::table('messages')
            ->whereBetween('created_at', [$from, $to])
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.generated_by')) = 'no_answer'")
            ->distinct()
            ->count('conversation_id');

        return [
            'questions' => $total,
            'unanswered' => $unanswered,
            'rate' => $this->rate($unanswered, $total),
            'conversations_affected' => $affected,
        ];
    }

    /** @return array<string, mixed> */
    private function csat(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = DB::table('conversation_ratings')
            ->whereBetween('rated_at', [$from, $to])
            ->selectRaw('COUNT(*) AS responses, AVG(score) AS average, SUM(score >= 4) AS positive')
            ->first();

        $responses = (int) ($row->responses ?? 0);

        return [
            'responses' => $responses,
            'average' => $responses ? round((float) $row->average, 2) : null,
            'positive_rate' => $this->rate((int) ($row->positive ?? 0), $responses),
        ];
    }

    /**
     * How long a customer waits for the bot's answer, measured from their own
     * message to the reply they receive.
     *
     * `ai_runs.latency_ms` is more precise but covers only the Agent engine
     * and excludes queue time, so it measures the model rather than the wait.
     * This measures the wait, on both engines — which is the number a buyer
     * cares about.
     *
     * MySQL 8 has no percentile function, so the percentiles are taken
     * positionally over the ordered set.
     *
     * @return array{samples: int, p50_seconds: int|null, p95_seconds: int|null}
     */
    private function answerLatency(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = DB::table('messages as reply')
            ->whereBetween('reply.created_at', [$from, $to])
            ->where('reply.direction', 'outbound')
            ->whereIn('reply.sender_type', self::AI_SENDERS)
            ->selectRaw('TIMESTAMPDIFF(SECOND, (SELECT MAX(ask.created_at) FROM messages ask WHERE ask.conversation_id = reply.conversation_id AND ask.direction = ? AND ask.created_at <= reply.created_at), reply.created_at) AS seconds', ['inbound']);

        $samples = DB::query()->fromSub($base, 'latencies')->whereNotNull('seconds')->count();

        if ($samples === 0) {
            return ['samples' => 0, 'p50_seconds' => null, 'p95_seconds' => null];
        }

        // Nearest-rank: the p95 of three samples is the third, not the second.
        // Interpolating between neighbours would be defensible too, but this
        // never reports a wait nobody actually experienced.
        $at = function (float $percentile) use ($base, $samples): ?int {
            $offset = max(0, min($samples - 1, (int) ceil($percentile * $samples) - 1));

            $value = DB::query()->fromSub($base, 'latencies')
                ->whereNotNull('seconds')
                ->orderBy('seconds')
                ->offset($offset)
                ->limit(1)
                ->value('seconds');

            return $value === null ? null : (int) $value;
        };

        return ['samples' => $samples, 'p50_seconds' => $at(0.50), 'p95_seconds' => $at(0.95)];
    }

    /**
     * What the AI spent, and what that works out to per conversation.
     *
     * Sourced entirely from the tenant's own `ai_runs`, which now covers both
     * engines: the Agent path always wrote runs, and the widget's
     * knowledge-answer path records one per generated answer. Before that it
     * did not, and a workspace running web chat alone saw a cost of zero —
     * a number indistinguishable from "the bot is free".
     *
     * @return array<string, mixed>
     */
    private function cost(CarbonInterface $from, CarbonInterface $to, int $conversations): array
    {
        $row = DB::table('ai_runs')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('SUM(estimated_cost) AS total, SUM(total_token_count) AS tokens, COUNT(*) AS runs, COUNT(DISTINCT conversation_id) AS conversations')
            ->first();

        $total = (float) ($row->total ?? 0);

        return [
            'total' => round($total, 4),
            'tokens' => (int) ($row->tokens ?? 0),
            'runs' => (int) ($row->runs ?? 0),
            'per_conversation' => $conversations > 0 ? round($total / $conversations, 4) : null,
            'attributed_conversations' => (int) ($row->conversations ?? 0),
            // Runs recorded before this covered both engines are attributed
            // to no conversation, so historical per-conversation cost reads
            // low rather than wrong.
            'covers' => 'agent+widget',
        ];
    }

    /**
     * The unanswered questions worth writing an article about, highest volume
     * first. Not date-filtered: a gap is a standing fact about the corpus, and
     * one that stopped being asked because customers gave up is still a gap.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topGaps(int $limit = 8): array
    {
        return DB::table('knowledge_gaps')
            ->where('status', 'open')
            ->orderByDesc('occurrences')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get(['uuid', 'question', 'origin', 'occurrences', 'best_score', 'last_seen_at'])
            ->map(fn ($row) => [
                'uuid' => $row->uuid,
                'question' => $row->question,
                'origin' => $row->origin,
                'occurrences' => (int) $row->occurrences,
                'best_score' => $row->best_score === null ? null : round((float) $row->best_score, 3),
                'last_seen_at' => $row->last_seen_at,
            ])
            ->all();
    }

    /** Percentage to one decimal place, or null when there is nothing to divide by. */
    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
