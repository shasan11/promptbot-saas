<?php

namespace App\Enums\Inbox;

/**
 * Who currently owns replying to a conversation.
 *
 * This was previously implicit, inferred from whichever proxy each caller
 * happened to use — `AutonomousReplyService` checked "is the latest message
 * inbound", `WebChatAutoReplyService` checked "does an autonomous agent
 * exist". Two independent systems guessing at the same fact is what allowed
 * an AI reply to land on a conversation a human had already picked up, and
 * what made "AI stops when a human takes over" impossible to state clearly.
 *
 * Making it a column makes the rule enforceable in one place and renderable
 * in the Inbox.
 */
enum ControlState: string
{
    /** AI may answer automatically. The default for a new conversation. */
    case Ai = 'ai';

    /** Escalated and waiting for a person. AI must not reply. */
    case PendingHuman = 'pending_human';

    /** A human has replied. AI must not auto-reply again; copilot stays available. */
    case Human = 'human';

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'AI handling',
            self::PendingHuman => 'Waiting for a teammate',
            self::Human => 'Handled by a teammate',
        };
    }

    /** The single question every auto-reply path must ask before generating. */
    public function allowsAutomatedReply(): bool
    {
        return $this === self::Ai;
    }

    /** Whether a human is expected to act — drives Inbox badging and routing. */
    public function needsHuman(): bool
    {
        return $this === self::PendingHuman;
    }
}
