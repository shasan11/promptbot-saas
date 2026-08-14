<?php

namespace App\Services\Channels;

use App\Enums\Knowledge\RetrievalMode;
use App\Models\Channel\WebChatWidget;
use App\Models\Inbox\Conversation;
use App\Models\Knowledge\KnowledgeRetrievalLog;
use App\Services\AI\AIFeatureManager;
use App\Services\Knowledge\Data\RetrievalQuery;
use App\Services\Knowledge\KnowledgeAnswerService;
use App\Services\Knowledge\KnowledgeRetrievalService;
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
 */
class WebChatAutoReplyService
{
    private const FEATURE_KEY = 'knowledge_answers';

    public function __construct(
        private readonly AIFeatureManager $features,
        private readonly KnowledgeRetrievalService $retrieval,
        private readonly KnowledgeAnswerService $answers,
    ) {}

    public function maybeReply(WebChatWidget $widget, Conversation $conversation, string $question): void
    {
        if (! $widget->ai_auto_reply_enabled || ! $widget->knowledge_base_id) {
            return;
        }

        if (! $this->features->isEnabled(self::FEATURE_KEY)) {
            return;
        }

        try {
            $this->reply($widget, $conversation, $question);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function reply(WebChatWidget $widget, Conversation $conversation, string $question): void
    {
        $base = $widget->knowledgeBase;

        if (! $base) {
            return;
        }

        $query = new RetrievalQuery(
            query: $question,
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
        $answer = $this->answers->answer($outcome, $question);

        // Never let an unconfigured/failed AI call post a "could not find
        // enough matching knowledge" bot message with high confidence — only
        // post when there was something real to say.
        if ($outcome->isEmpty() || trim((string) ($answer['answer'] ?? '')) === '') {
            return;
        }

        DB::transaction(function () use ($conversation, $answer): void {
            $conversation->messages()->create([
                'sender_type' => 'ai',
                'sender_id' => null,
                'sender_name' => 'AI Assistant',
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => $answer['answer'],
                'status' => 'delivered',
                'sent_at' => now(),
                'metadata' => [
                    'ai_generated' => true,
                    'generated_by' => $answer['generated_by'] ?? null,
                    'model' => $answer['model'] ?? null,
                    'confidence' => $answer['confidence'] ?? null,
                ],
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'message_count' => DB::raw('message_count + 1'),
            ]);
        });
    }
}
