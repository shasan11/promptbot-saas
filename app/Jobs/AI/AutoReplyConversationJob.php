<?php

namespace App\Jobs\AI;

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
 * Retained for backwards compatibility.
 *
 * Reply dispatch now goes through `GenerateConversationReplyJob`, and the
 * agent-selection logic this job used to inline has moved to
 * `AutonomousReplyService::replyWithAgent()`. Nothing in the codebase
 * dispatches this any more, but it is delegated rather than deleted so that
 * any existing queued payload or external caller still routes through the
 * one orchestrator instead of bypassing the control-state and escalation
 * gates it now owns.
 */
class AutoReplyConversationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 2;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    public function __construct(private readonly int $conversationId)
    {
        $this->captureTenant();
        $this->onQueue(config('ai.queues.high'));
    }

    public function uniqueId(): string
    {
        return ($this->tenantId ?? 'central').':'.$this->conversationId;
    }

    public function handle(ConversationReplyOrchestrator $orchestrator): void
    {
        $conversation = Conversation::query()
            ->with(['channel.webChatWidget', 'contact', 'latestMessage'])
            ->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        // The original signature carried no message text, so the triggering
        // message is recovered from the thread.
        $latest = $conversation->latestMessage;

        if (! $latest || $latest->direction !== 'inbound') {
            return;
        }

        $orchestrator->handleInbound($conversation, (string) $latest->body);
    }
}
