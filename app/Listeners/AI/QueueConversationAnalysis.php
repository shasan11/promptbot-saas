<?php

namespace App\Listeners\AI;

use App\Events\Inbox\ConversationReceived;
use App\Jobs\AI\AnalyzeConversationJob;

class QueueConversationAnalysis
{
    public function handle(ConversationReceived $event): void { AnalyzeConversationJob::dispatch($event->conversation->id); }
}
