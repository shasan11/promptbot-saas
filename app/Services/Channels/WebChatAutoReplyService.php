<?php

namespace App\Services\Channels;

use App\Enums\AI\RunStatus;
use App\Enums\Knowledge\RetrievalMode;
use App\Models\AI\Run;
use App\Models\Channel\WebChatWidget;
use App\Models\Inbox\Conversation;
use App\Models\Knowledge\KnowledgeRetrievalLog;
use App\Services\AI\AIFeatureManager;
use App\Services\Knowledge\Data\RetrievalQuery;
use App\Services\Knowledge\KnowledgeAnswerService;
use App\Services\Knowledge\KnowledgeRetrievalService;
use App\Services\Knowledge\QueryRewriteService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Answers an inbound web-chat message automatically, using the same
 * retrieval + generation path as the tenant Knowledge Playground and
 * Inbox — the widget is just another consumer of KnowledgeAnswerService.
 *
 * A failure here must never break message delivery: the visitor's message
 * is already stored by the time this runs, so any error (AI not configured,
 * provider down, no knowledge base assigned) is swallowed and reported —
 * the visitor simply waits for a human agent, exactly as they would today.
 *
 * This is a simpler, widget-only auto-reply path, independent of the
 * Agent-based autonomous reply system (AutonomousReplyService), which is
 * queued asynchronously for every channel via AnalyzeConversationJob →
 * AutoReplyConversationJob. If a tenant has ALSO deployed an autonomous
 * Agent on this same channel, that system already owns replying here —
 * running both would risk sending the visitor two answers to one message,
 * so this simpler path defers to it.
 */
class WebChatAutoReplyService
{
    private const FEATURE_KEY = 'knowledge_answers';

    public function __construct(
        private readonly AIFeatureManager $features,
        private readonly KnowledgeRetrievalService $retrieval,
        private readonly KnowledgeAnswerService $answers,
        private readonly QueryRewriteService $rewriter,
    ) {}

    /**
     * Answer using the knowledge-only path.
     *
     * Control-state, human-request and risk gating now live in
     * `ConversationReplyOrchestrator`; the checks kept here are the ones that
     * are genuinely this engine's own preconditions — is this widget
     * configured to answer at all, and is the platform feature enabled.
     *
     * Returns false when nothing was sent, so the orchestrator can treat it
     * as an AI failure.
     */
    public function reply(WebChatWidget $widget, Conversation $conversation, string $question): bool
    {
        if (! $widget->ai_auto_reply_enabled || ! $widget->knowledge_base_id) {
            return false;
        }

        if (! $this->features->isEnabled(self::FEATURE_KEY)) {
            return false;
        }

        try {
            return $this->generate($widget, $conversation, $question);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Matches a message that is *entirely* a greeting/thanks/farewell —
     * "hi", "thanks!", "good morning" — but not "hi, what is promptbot",
     * which is a real question and must still go through retrieval. Kept
     * deliberately narrow: a false positive here (treating a real question
     * as smalltalk) would silently deny the customer an answer, which is a
     * worse failure than occasionally sending retrieval a "thanks".
     */
    private const SMALLTALK_PATTERN = '/^(hi+|hello+|hey+|hiya|yo|howdy|'
        .'good\s?(morning|afternoon|evening|day)|greetings|'
        .'thanks?( you)?( so much| a lot)?|thank\s?you|cheers|'
        .'bye|goodbye|see\s?ya|take\s?care)[\s!.,]*$/i';

    /**
     * Runs before any retrieval call. Forcing every "hey" or "thanks" through
     * knowledge retrieval + generation is what produced replies that read
     * like a document dump — there is no knowledge to synthesize for a
     * greeting, so the model had nothing to work with but the raw context.
     * A message that is purely smalltalk gets a fast, correct, zero-cost
     * reply instead of being coerced into an answer it can't give.
     */
    private function smalltalkReply(string $message): ?string
    {
        if (! preg_match(self::SMALLTALK_PATTERN, trim($message))) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/^(bye|goodbye|see\s?ya|take\s?care)/i', trim($message)) => 'Take care! Reach out anytime if you have more questions.',
            (bool) preg_match('/^(thanks?|thank\s?you|cheers)/i', trim($message)) => "You're welcome! Is there anything else I can help with?",
            default => 'Hi! How can I help you today?',
        };
    }

    /**
     * `KnowledgeAnswerService` writes inline citation markers like "[1]" or
     * "[1, 2]" into the answer text — genuinely useful in a UI built to
     * render them as clickable footnotes (the Knowledge Playground), but the
     * widget renders plain text with no way to look up what "[1]" refers to.
     * Left in, they read as broken/dead references to a customer. The
     * citation data itself isn't discarded — it's passed through separately
     * as `sources_used` metadata — this only cleans up what the customer sees.
     */
    private function stripCitationMarkers(string $text): string
    {
        $text = preg_replace('/\s*\[\d+(?:\s*,\s*\d+)*\]/', '', $text) ?? $text;
        $text = preg_replace('/\s+([.,!?;:])/', '$1', $text) ?? $text;
        $text = preg_replace('/\s{2,}/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function generate(WebChatWidget $widget, Conversation $conversation, string $question): bool
    {
        if ($smalltalk = $this->smalltalkReply($question)) {
            $this->post($conversation, $smalltalk, ['ai_generated' => true, 'generated_by' => 'smalltalk']);

            return true;
        }

        $base = $widget->knowledgeBase;

        if (! $base) {
            return false;
        }

        // Retrieval searches for the rewritten text; generation still answers
        // the customer's actual words. A follow-up like "how much does it
        // cost?" carries no subject on its own and would retrieve nothing.
        $searchText = $this->rewriter->rewrite($question, $conversation);

        $query = new RetrievalQuery(
            query: $searchText,
            knowledgeBaseIds: [$base->id],
            mode: $base->retrieval_mode ?? RetrievalMode::Hybrid,
            topK: $base->top_k ?? 5,
            candidatePool: $base->candidate_pool ?? 20,
            similarityThreshold: (float) ($base->similarity_threshold ?? 0.7),
            rerank: (bool) ($base->reranking_enabled ?? true),
            maxContextTokens: $base->max_context_tokens ?? 8000,
            channel: KnowledgeRetrievalLog::CHANNEL_WIDGET,
        );

        $outcome = $this->retrieval->retrieve($query, $base);
        $answer = $this->answers->answer($outcome, $question, $conversation->channel?->effectiveBotProfile()?->styleInstruction());

        // Retrieval found nothing usable. Previously this returned silently,
        // which the visitor experiences as the bot ignoring them — the single
        // most damaging failure mode the widget had. Say so plainly instead,
        // and (by default) pull a human in rather than leaving the question
        // to die in an unattended conversation.
        if ($outcome->isEmpty() || trim((string) ($answer['answer'] ?? '')) === '') {
            $this->postNoAnswer($widget, $conversation);

            return false;
        }

        $this->recordRun($conversation, $answer);

        $this->post($conversation, $this->stripCitationMarkers($answer['answer']), [
            'ai_generated' => true,
            'generated_by' => $answer['generated_by'] ?? null,
            'model' => $answer['model'] ?? null,
            // The citations themselves are not lost — sources_used carries
            // them structured, for whatever surface (analytics, a future
            // "sources" affordance) actually knows how to present them.
            'sources_used' => $answer['sources_used'] ?? [],
            'confidence' => $answer['confidence'] ?? null,
        ]);

        return true;
    }

    /**
     * Records what this answer cost against the conversation it answered.
     *
     * The widget's generation goes through `AIManager`, which logs to the
     * *central* platform usage table — operator-scoped, and with no notion of
     * a conversation. Without this row, per-conversation cost covered only
     * conversations an Agent answered, so a workspace running web chat alone
     * saw a cost of zero and a page that looked broken.
     *
     * Best-effort by design: an analytics row must never cost the visitor
     * their answer, which has already been generated by this point.
     *
     * @param  array<string, mixed>  $answer
     */
    private function recordRun(Conversation $conversation, array $answer): void
    {
        $usage = $answer['usage'] ?? null;

        // The extractive fallback makes no provider call, so there is nothing
        // to bill and no run to record.
        if (! is_array($usage)) {
            return;
        }

        try {
            Run::create([
                'feature' => 'widget_answer',
                'operation' => 'answer',
                'conversation_id' => $conversation->id,
                'provider' => $usage['provider'] ?? null,
                'model' => $usage['model'] ?? null,
                'status' => RunStatus::Completed,
                'started_at' => now()->subMilliseconds((int) ($usage['latency_ms'] ?? 0)),
                'finished_at' => now(),
                'latency_ms' => (int) ($usage['latency_ms'] ?? 0),
                'attempt_count' => 1,
                'input_token_count' => (int) ($usage['prompt_tokens'] ?? 0),
                'output_token_count' => (int) ($usage['completion_tokens'] ?? 0),
                'total_token_count' => (int) ($usage['total_tokens'] ?? 0),
                'estimated_cost' => (float) ($usage['estimated_cost'] ?? 0),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The "I don't know" path. Posting a message here rather than nothing is
     * what stops an unanswerable question from looking like a broken widget,
     * and flagging for handoff is what stops it from being forgotten: an
     * AI-only conversation nobody is assigned to would otherwise sit unread.
     */
    private function postNoAnswer(WebChatWidget $widget, Conversation $conversation): void
    {
        $this->post(
            $conversation,
            trim((string) $widget->no_answer_message) ?: WebChatWidget::DEFAULT_NO_ANSWER_MESSAGE,
            ['ai_generated' => true, 'generated_by' => 'no_answer'],
        );

        if (! $widget->handoff_on_no_answer) {
            return;
        }

        // Reopen and surface it: `unread_count` is what the Inbox badges, so
        // an unanswered question becomes visible work for a human instead of
        // a silently-closed thread.
        //
        // Written through the query builder rather than the model instance on
        // purpose: post() above already ran `update(['message_count' =>
        // DB::raw(...)])` on this same instance, which leaves an Expression
        // object sitting in its attributes. Saving the model again risks
        // re-persisting that raw expression, double-incrementing the counter.
        // A targeted UPDATE touches only the two columns intended here.
        Conversation::query()->whereKey($conversation->id)->update([
            'status' => DB::raw("CASE WHEN status IN ('closed','resolved','snoozed') THEN 'open' ELSE status END"),
            'unread_count' => DB::raw('unread_count + 1'),
            'updated_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $metadata */
    private function post(Conversation $conversation, string $body, array $metadata): void
    {
        DB::transaction(function () use ($conversation, $body, $metadata): void {
            $conversation->messages()->create([
                'sender_type' => 'ai',
                'sender_id' => null,
                'sender_name' => 'AI Assistant',
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => $body,
                'status' => 'delivered',
                'sent_at' => now(),
                'metadata' => $metadata,
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'message_count' => DB::raw('message_count + 1'),
            ]);
        });
    }
}
