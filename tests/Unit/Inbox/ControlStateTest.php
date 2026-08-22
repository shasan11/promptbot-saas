<?php

namespace Tests\Unit\Inbox;

use App\Enums\Inbox\ControlState;
use Tests\TestCase;

/**
 * The enum is the whole contract for "may the AI answer this?". Every
 * auto-reply path asks it exactly one question, so a wrong answer here is a
 * bot talking over a human — the failure this column was introduced to stop.
 */
class ControlStateTest extends TestCase
{
    public function test_only_the_ai_state_permits_an_automated_reply(): void
    {
        $this->assertTrue(ControlState::Ai->allowsAutomatedReply());
        $this->assertFalse(ControlState::PendingHuman->allowsAutomatedReply());
        $this->assertFalse(ControlState::Human->allowsAutomatedReply());
    }

    public function test_only_the_pending_state_asks_for_a_human(): void
    {
        // `human` means a teammate already answered — the conversation is not
        // waiting on anybody, so badging it as "needs a human" would put
        // handled threads back in the escalation queue.
        $this->assertTrue(ControlState::PendingHuman->needsHuman());
        $this->assertFalse(ControlState::Ai->needsHuman());
        $this->assertFalse(ControlState::Human->needsHuman());
    }

    public function test_every_case_has_a_distinct_non_empty_label(): void
    {
        $labels = array_map(fn (ControlState $state) => $state->label(), ControlState::cases());

        $this->assertCount(count(ControlState::cases()), array_unique($labels));
        $this->assertNotContains('', $labels);
    }

    /**
     * The stored strings are persisted in `conversations.control_state` and
     * backfilled literally by the migration. Renaming one silently reclassifies
     * every existing row, so the values are pinned here on purpose.
     */
    public function test_stored_values_are_stable(): void
    {
        $this->assertSame('ai', ControlState::Ai->value);
        $this->assertSame('pending_human', ControlState::PendingHuman->value);
        $this->assertSame('human', ControlState::Human->value);
    }
}
