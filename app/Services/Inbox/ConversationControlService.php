<?php

namespace App\Services\Inbox;

use App\Enums\Inbox\ControlState;
use App\Models\Inbox\Conversation;
use App\Models\User;
use App\Services\Customer\CustomerTimelineService;
use App\Services\Operations\RoutingService;
use Illuminate\Support\Facades\DB;

/**
 * Owns every transition of who is answering a conversation.
 *
 * Centralised deliberately: the rule "a human taking over stops the AI" is
 * only trustworthy if there is exactly one place that can move control, and
 * only one place that decides what that means for routing and unread state.
 */
class ConversationControlService
{
    public function __construct(
        private readonly RoutingService $routing,
        private readonly CustomerTimelineService $timeline,
    ) {}

    /**
     * Escalate to a person. Idempotent: repeated escalations (three failed
     * answers in a row, then the customer also typing "agent") must not
     * re-route or re-notify a conversation that is already queued.
     */
    public function escalate(Conversation $conversation, string $reason, ?string $detail = null, ?int $teamId = null): bool
    {
        if ($conversation->control_state === ControlState::Human || $conversation->control_state === ControlState::PendingHuman) {
            return false;
        }

        DB::transaction(function () use ($conversation, $teamId): void {
            $conversation->forceFill([
                'control_state' => ControlState::PendingHuman,
                'control_changed_at' => now(),
                // Escalated work must be visible; a resolved/closed thread
                // that just got escalated is not resolved.
                'status' => in_array($conversation->status, ['closed', 'resolved', 'snoozed'], true) ? 'open' : $conversation->status,
            ])->save();

            // Route only if nobody owns it — an escalation must never yank a
            // conversation away from the agent already working it.
            if ($conversation->assignee_id) {
                return;
            }

            // An explicitly configured escalation team wins over generic
            // routing rules: the point of naming a team on the bot profile is
            // that escalations go there specifically.
            if ($teamId) {
                $conversation->forceFill(['team_id' => $teamId])->save();

                return;
            }

            $this->routing->route($conversation);
        });

        $this->timeline->record(
            'conversation.escalated',
            trim("Escalated to a human teammate: {$reason}".($detail ? " — {$detail}" : '')),
            $conversation->contact,
            related: $conversation,
            metadata: ['reason' => $reason],
        );

        return true;
    }

    /**
     * A human replied. From here the AI must not auto-reply again on this
     * conversation, regardless of what any agent config says.
     */
    public function humanTookOver(Conversation $conversation, ?User $actor = null): void
    {
        if ($conversation->control_state === ControlState::Human) {
            return;
        }

        $conversation->forceFill([
            'control_state' => ControlState::Human,
            'control_changed_at' => now(),
            // The counter describes an AI run of failures; a human answering
            // ends that run.
            'ai_failure_count' => 0,
        ])->save();

        $this->timeline->record(
            'conversation.human_takeover',
            trim(($actor?->name ?: 'A teammate').' took over from the AI assistant.'),
            $conversation->contact,
            actor: $actor,
            related: $conversation,
        );
    }

    /**
     * Hand a conversation back to the AI — used when a thread is resolved and
     * a later message should start fresh rather than permanently occupying a
     * human. Never applied to a conversation still waiting on a person.
     */
    public function returnToAi(Conversation $conversation): void
    {
        if ($conversation->control_state === ControlState::PendingHuman) {
            return;
        }

        $conversation->forceFill([
            'control_state' => ControlState::Ai,
            'control_changed_at' => now(),
            'ai_failure_count' => 0,
        ])->save();
    }

    /**
     * Records that the AI could not answer, and escalates once the configured
     * tolerance is exhausted. Returns true when this call caused an escalation.
     */
    public function recordAiFailure(Conversation $conversation, int $threshold = 2, ?int $teamId = null): bool
    {
        $count = $conversation->ai_failure_count + 1;
        $conversation->forceFill(['ai_failure_count' => $count])->save();

        // A threshold of 0 means the tenant turned failure-escalation off; a
        // negative one would otherwise escalate on the very first failure.
        if ($threshold < 1 || $count < $threshold) {
            return false;
        }

        return $this->escalate($conversation, 'repeated_no_answer', "AI could not answer {$count} question(s) in a row", $teamId);
    }
}
