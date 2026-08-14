<?php

namespace App\Services\Inbox;

use App\Enums\AI\AIPurpose;
use App\Models\Inbox\Conversation;
use App\Models\Inbox\Message;
use App\Services\AI\AIFeatureManager;
use App\Services\AI\AIManager;
use App\Services\AI\Data\ChatMessage;
use App\Services\AI\Data\ChatRequest;

/**
 * AI assistance for agents working a conversation: drafting a reply and
 * summarizing a thread. Both read the same recent message history and are
 * gated by the same `inbox_reply_suggestions` feature — agents who can use
 * one can use the other, they're two views of "AI help me with this thread".
 *
 * Callers (the controller) are expected to catch AIException and surface
 * `operatorMessage()` — nothing here silently degrades, because unlike RAG
 * answers/auto-replies, a draft/summary is always agent-requested and
 * agent-reviewed before it reaches a customer, so failing loudly (rather than
 * substituting a fake draft) is the correct behavior.
 */
class InboxAIService
{
    private const FEATURE_KEY = 'inbox_reply_suggestions';

    private const HISTORY_LIMIT = 20;

    public function __construct(
        private readonly AIManager $ai,
        private readonly AIFeatureManager $features,
    ) {}

    /** @return array<string, mixed> */
    public function draftReply(Conversation $conversation): array
    {
        $this->features->assertEnabled(self::FEATURE_KEY);

        $result = $this->ai->forPurpose(AIPurpose::InboxReplyDraft)->chat(new ChatRequest(
            messages: [
                new ChatMessage('system', $this->draftSystemPrompt($conversation)),
                ...$this->threadMessages($conversation),
            ],
            temperature: 0.4,
            maxTokens: 500,
        ));

        return ['draft' => trim($result->content), 'model' => $result->model];
    }

    /** @return array<string, mixed> */
    public function summarize(Conversation $conversation): array
    {
        $this->features->assertEnabled(self::FEATURE_KEY);

        $result = $this->ai->forPurpose(AIPurpose::ConversationSummary)->chat(new ChatRequest(
            messages: [
                new ChatMessage('system', <<<'PROMPT'
                    Summarize this support conversation for a teammate taking over.
                    Be concise: 3-5 bullet points covering the customer's issue, what has
                    already been tried or said, and the current status. Do not invent
                    details that are not in the conversation.
                    PROMPT),
                ...$this->threadMessages($conversation),
            ],
            temperature: 0.2,
            maxTokens: 400,
        ));

        return ['summary' => trim($result->content), 'model' => $result->model];
    }

    /** @return array<int, ChatMessage> */
    private function threadMessages(Conversation $conversation): array
    {
        return $conversation->messages()
            ->where('direction', '!=', 'internal')
            ->whereNotNull('body')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn (Message $message) => new ChatMessage(
                $message->direction === 'inbound' ? 'user' : 'assistant',
                trim((string) $message->body),
            ))
            ->filter(fn (ChatMessage $message) => $message->content !== '')
            ->values()
            ->all();
    }

    private function draftSystemPrompt(Conversation $conversation): string
    {
        $customer = $conversation->contact?->display_name ?? 'the customer';

        return "You are a support agent drafting a reply to {$customer}. Be helpful, ".
            'professional, and concise. Write only the reply body — no subject line, '.
            'no signature, no meta-commentary about being an AI.';
    }
}
