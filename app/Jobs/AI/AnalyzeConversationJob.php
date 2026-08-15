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

class AnalyzeConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;
    public int $tries = 2; public int $timeout = 180;
    public function __construct(private readonly int $conversationId) { $this->captureTenant(); $this->onQueue(config('ai.queues.analysis')); }
    public function handle(InboxCopilotService $copilot, AISettingsService $settings): void
    {
        $configuration = $settings->current();
        if (! $configuration['enabled'] || ! $configuration['background_inbox_analysis']) return;
        $conversation = Conversation::query()->find($this->conversationId); if (! $conversation) return;
        $needsSummary = ! $conversation->aiInsight()->exists() || $conversation->message_count % 5 === 0;
        $copilot->classify($conversation);
        if ($needsSummary) $copilot->perform($conversation, 'summary');
        AutoReplyConversationJob::dispatch($conversation->id);
    }
}
