<?php

namespace App\Jobs\AI;

use App\Jobs\Concerns\TenantAware;
use App\Models\Ticket\Ticket;
use App\Services\AI\AISettingsService;
use App\Services\AI\TicketCopilotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(private readonly int $ticketId)
    {
        $this->captureTenant();
        $this->onQueue(config('ai.queues.analysis'));
    }

    public function handle(TicketCopilotService $copilot, AISettingsService $settings): void
    {
        if (! $settings->current()['enabled']) {
            return;
        }

        $ticket = Ticket::query()->find($this->ticketId);

        if ($ticket) {
            $copilot->perform($ticket, 'summary');
        }
    }
}
