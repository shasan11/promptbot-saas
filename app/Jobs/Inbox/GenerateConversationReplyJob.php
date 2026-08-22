<?php

namespace App\Jobs\Inbox;

use App\Jobs\Concerns\TenantAware;
use App\Models\Inbox\Conversation;
use App\Services\Inbox\ConversationReplyOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the reply decision off the request thread.
 *
 * Retrieval plus an LLM call previously happened inside the visitor's own
 * POST to /widget/api/{key}/messages, which held a PHP-FPM worker for the
 * full generation time and delayed the visitor seeing their *own* message.
 * The message is durably stored before this is dispatched, so a failure here
 * costs an AI reply, never the customer's question.
 *
 * `ShouldBeUnique` keyed on the conversation prevents two concurrent
 * generations answering the same thread twice when a customer sends several
 * messages quickly.
 */
class GenerateConversationReplyJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 2;

    public int $timeout = 180;

    /**
     * Short on purpose. The lock only needs to cover one generation; holding
     * it longer would drop the follow-up message a customer sends while the
     * bot is still answering the previous one.
     */
    public int $uniqueFor = 120;

    public function __construct(private readonly int $conversationId, private readonly string $message)
    {
        $this->captureTenant();
        $this->onQueue(config('ai.queues.high'));
    }

    public function uniqueId(): string
    {
        return ($this->tenantId ?? 'central').':reply:'.$this->conversationId;
    }

    public function handle(ConversationReplyOrchestrator $orchestrator): void
    {
        $conversation = Conversation::query()
            ->with(['channel.webChatWidget', 'contact'])
            ->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        $orchestrator->handleInbound($conversation, $this->message);
    }
}
