<?php

namespace App\Services\AI;

use App\Models\AI\Agent;
use App\Models\AI\Suggestion;
use App\Models\Ticket\Ticket;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketCopilotService
{
    public function __construct(private readonly TenantAgentRuntime $runtime) {}

    public function perform(Ticket $ticket, string $operation, ?User $actor = null): array
    {
        $agent = Agent::query()->where('status', 'active')->orderBy('id')->first();
        if (! $agent) throw ValidationException::withMessages(['agent' => 'Deploy an AI agent before using ticket AI.']);
        $ticket->loadMissing('comments');
        $context = "Ticket {$ticket->ticket_number}\nSubject: {$ticket->subject}\nDescription: {$ticket->description}\nPriority: {$ticket->priority}\nComments:\n".$ticket->comments->take(-30)->map(fn ($comment) => ($comment->internal ? 'INTERNAL' : 'PUBLIC').': '.Str::limit($comment->body, 5000))->implode("\n---\n");
        $instruction = match ($operation) {
            'summary' => 'Summarize the ticket goal, facts, work completed, blockers, and current state without adding facts.',
            'draft' => 'Draft a concise response or internal resolution note. Ground factual business claims in permitted workspace knowledge and cite sources. Never claim an action was performed.',
            'action_items' => 'Extract a concise checklist of concrete unresolved action items. Separate suggested work from completed work.',
            default => throw ValidationException::withMessages(['operation' => 'Unsupported ticket AI operation.']),
        };
        $result = $this->runtime->chat($agent, $instruction."\n\n<UNTRUSTED-TICKET>\n{$context}\n</UNTRUSTED-TICKET>", $actor, 'ticket_copilot', $operation, $operation === 'draft');
        Suggestion::query()->where('public_uuid', $result['suggestion_uuid'])->update(['resource_type' => Ticket::class, 'resource_id' => $ticket->id, 'ticket_id' => $ticket->id, 'type' => $operation]);
        return $result;
    }
}
