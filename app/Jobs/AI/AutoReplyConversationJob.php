<?php

namespace App\Jobs\AI;

use App\Jobs\Concerns\TenantAware;
use App\Models\AI\Suggestion;
use App\Models\Inbox\Conversation;
use App\Services\AI\AutonomousReplyService;
use App\Services\AI\InboxCopilotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoReplyConversationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;
    public int $tries = 2; public int $timeout = 180; public int $uniqueFor = 300;
    public function __construct(private readonly int $conversationId) { $this->captureTenant(); $this->onQueue(config('ai.queues.high')); }
    public function uniqueId(): string { return ($this->tenantId ?? 'central').':'.$this->conversationId; }
    public function handle(InboxCopilotService $copilot, AutonomousReplyService $sender): void
    {
        $conversation = Conversation::query()->with(['channel','contact','aiInsight','latestMessage'])->find($this->conversationId);
        if (! $conversation || ! ($agent = $sender->eligibleAgent($conversation))) return;
        $result = $copilot->perform($conversation, 'draft', null, [], $agent);
        $suggestion = Suggestion::query()->where('public_uuid', $result['suggestion_uuid'])->first();
        if ($suggestion) $sender->send($conversation->fresh(), $agent, $suggestion);
    }
}
