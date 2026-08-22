<?php

namespace App\Jobs\AI;

use App\Jobs\Concerns\TenantAware;
use App\Models\Inbox\Conversation;
use App\Services\AI\AISettingsService;
use App\Services\AI\InboxCopilotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Produces the conversation insights the Inbox and escalation rules read
 * (intent, sentiment, urgency, risk flags, rolling summary).
 *
 * It no longer dispatches the auto-reply — `QueueConversationAnalysis` now
 * dispatches replies directly so answering a customer never queues behind
 * classification.
 */
class AnalyzeConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(private readonly int $conversationId)
    {
        $this->captureTenant();
        $this->onQueue(config('ai.queues.analysis'));
    }

    public function handle(InboxCopilotService $copilot, AISettingsService $settings): void
    {
        $configuration = $settings->current();

        if (! $configuration['enabled'] || ! $configuration['background_inbox_analysis']) {
            return;
        }

        $conversation = Conversation::query()->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        // Classification drives escalation, so it runs on every message and
        // stays cheap (one structured call). The summary is a human
        // convenience that costs a second full generation, so it is produced
        // once the thread is long enough to be worth summarising and then
        // only periodically — previously both ran on every single inbound
        // message, which meant two LLM calls per customer message before a
        // reply was even attempted.
        // Classification is an enhancement, and it must never take the
        // conversation down with it. `agentFor()` throws a ValidationException
        // when no agent has been deployed — on a sync queue that propagates
        // straight into the inbound webhook request, turning the provider's
        // 202 into a 302 redirect and making a delivered message look like a
        // failed one. Analysis failing costs sentiment and risk flags; message
        // ingestion failing costs the customer's question.
        try {
            $copilot->classify($conversation);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if ($this->needsSummary($conversation)) {
            // A failed summary must not fail the job and lose the
            // classification that already succeeded.
            try {
                $copilot->perform($conversation, 'summary');
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function needsSummary(Conversation $conversation): bool
    {
        $count = (int) $conversation->message_count;

        // Nothing to summarise in a two-message exchange the agent can read
        // at a glance.
        if ($count < 6) {
            return false;
        }

        return ! $conversation->aiInsight()->whereNotNull('summary')->exists() || $count % 10 === 0;
    }
}
