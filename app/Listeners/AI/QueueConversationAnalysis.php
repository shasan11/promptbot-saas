<?php

namespace App\Listeners\AI;

use App\Events\Inbox\ConversationReceived;
use App\Jobs\AI\AnalyzeConversationJob;
use App\Jobs\Inbox\GenerateConversationReplyJob;

/**
 * The single fan-out point for an inbound customer message.
 *
 * Every channel reaches here through `ConversationService::receive()`, so
 * dispatching from this one listener is what keeps web chat and every other
 * channel on the same reply path — the widget used to answer inline in its
 * own controller while other channels went through the Agent job, which is
 * how two systems ended up racing for the same conversation.
 *
 * Reply and analysis are dispatched independently, and the reply goes on the
 * high-priority queue: answering the customer must not wait behind
 * classification and summarisation. The cost is that the very first message
 * in a conversation is answered before its risk classification exists, so
 * risk-based escalation begins from the second message onward. An explicit
 * "I want a human" is matched textually by the orchestrator and is therefore
 * still honoured immediately, which is the case that actually matters.
 */
class QueueConversationAnalysis
{
    public function handle(ConversationReceived $event): void
    {
        // Only a customer message should trigger an automated answer —
        // outbound and internal notes must never cause the bot to reply to
        // itself or to an agent's own note.
        if ($event->message->direction === 'inbound') {
            GenerateConversationReplyJob::dispatch($event->conversation->id, (string) $event->message->body);
        }

        AnalyzeConversationJob::dispatch($event->conversation->id);
    }
}
