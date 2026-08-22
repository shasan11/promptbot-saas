<?php

namespace App\Services\Inbox;

use App\Enums\Inbox\ControlState;
use App\Models\Channel\BotProfile;
use App\Models\Inbox\Conversation;
use App\Services\AI\AutonomousReplyService;
use App\Services\Channels\WebChatAutoReplyService;
use Illuminate\Support\Str;

/**
 * The single entry point that decides how an inbound customer message gets
 * answered.
 *
 * Before this existed, two independent systems raced for the same message:
 * `WebChatAutoReplyService` ran synchronously inside the visitor's HTTP
 * request, while `AnalyzeConversationJob → AutoReplyConversationJob` ran the
 * Agent path on a queue. They coordinated only by
 * `WebChatAutoReplyService::hasAutonomousAgent()` — a check that deferred to
 * the Agent path without verifying the Agent path would actually reply. When
 * it didn't (see the recipient bug), nobody replied at all.
 *
 * Everything now flows through here, in one order, reading one explicit
 * control state:
 *
 *   control state gate → customer-intent gate → engine selection → reply
 *
 * The two reply services survive as *engines*; this class owns the decision.
 */
class ConversationReplyOrchestrator
{
    /**
     * Phrases that mean "stop talking to me, get me a person". Matched
     * conservatively — a false positive costs one unnecessary handoff, while
     * ignoring a real request is the single most frustrating thing a support
     * bot can do, so the trade is deliberately asymmetric.
     */
    private const HUMAN_REQUEST_PATTERN = '/\b(speak|talk|chat)\s+(to|with)\s+(an?\s+)?(human|person|agent|someone|somebody|representative|rep)\b'
        .'|\b(real|live)\s+(human|person|agent)\b'
        .'|\b(human|agent)\s+please\b'
        .'|\bcustomer\s+(service|support)\s+(agent|rep|representative)\b'
        .'|\b(get|connect)\s+me\s+(to|with)\s+(an?\s+)?(human|person|agent)\b/i';

    public function __construct(
        private readonly ConversationControlService $control,
        private readonly AutonomousReplyService $autonomous,
        private readonly WebChatAutoReplyService $widgetReply,
    ) {}

    /**
     * @param  string  $message  The inbound customer text that triggered this.
     */
    public function handleInbound(Conversation $conversation, string $message): void
    {
        $conversation->loadMissing(['channel.webChatWidget', 'channel.botProfile', 'contact']);

        // Never null — a channel with no profile falls back to documented
        // defaults matching the values these rules used when hardcoded.
        $profile = $conversation->channel?->effectiveBotProfile() ?? BotProfile::defaults();

        // 1. Explicit request for a person always wins, before any generation
        //    cost is incurred and regardless of how confident the AI is.
        if ($profile->escalate_on_request && $this->wantsHuman($message)) {
            $this->control->escalate($conversation, 'customer_requested_human', teamId: $profile->escalation_team_id);

            return;
        }

        // 2. Control gate. A conversation a human owns — or one already
        //    queued for a human — must never receive an automated reply.
        if (! $conversation->control_state->allowsAutomatedReply()) {
            return;
        }

        // 3. Risk gate. `ConversationInsight` already computes sentiment and
        //    risk flags on every inbound message; nothing consumed them for
        //    routing until now.
        if ($this->shouldEscalateOnRisk($conversation, $profile)) {
            return;
        }

        // 4. Engine selection. An explicitly deployed autonomous Agent is a
        //    deliberate configuration and outranks the widget's simpler
        //    knowledge-only path; the widget path is the fallback for
        //    workspaces that never set an Agent up.
        $replied = false;

        if ($agent = $this->autonomous->eligibleAgent($conversation)) {
            $replied = $this->autonomous->replyWithAgent($conversation, $agent);
        } elseif ($widget = $conversation->channel?->webChatWidget) {
            $replied = $this->widgetReply->reply($widget, $conversation, $message);
        } else {
            // No engine is configured for this channel at all, so there is no
            // AI failure to record — this conversation was always destined
            // for a human, and the Inbox already shows it as unread.
            return;
        }

        // 5. A reply that did not happen is a failure the customer can feel.
        //    Counting it is what makes "hand off after N unanswerable
        //    questions" work instead of letting the bot fail indefinitely.
        if (! $replied) {
            $this->control->recordAiFailure($conversation, $profile->escalate_after_failures, $profile->escalation_team_id);
        }
    }

    private function wantsHuman(string $message): bool
    {
        return (bool) preg_match(self::HUMAN_REQUEST_PATTERN, Str::lower($message));
    }

    /**
     * Escalates when the classifier flagged the conversation as risky or the
     * customer is clearly unhappy. Returns true when it escalated.
     */
    private function shouldEscalateOnRisk(Conversation $conversation, BotProfile $profile): bool
    {
        $insight = $conversation->aiInsight()->first();

        if (! $insight) {
            return false;
        }

        if ($profile->escalate_on_risk_flags && ($insight->risk_flags ?? []) !== []) {
            return $this->control->escalate(
                $conversation,
                'risk_flagged',
                implode(', ', array_slice((array) $insight->risk_flags, 0, 3)),
                $profile->escalation_team_id,
            );
        }

        if ($profile->escalate_on_negative_sentiment && $insight->sentiment === 'negative' && in_array($insight->urgency, ['high', 'urgent'], true)) {
            return $this->control->escalate($conversation, 'negative_sentiment', teamId: $profile->escalation_team_id);
        }

        return false;
    }
}
