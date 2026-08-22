<?php

namespace App\Services\AI;

use App\Enums\AI\AgentStatus;
use App\Enums\AI\DeploymentMode;
use App\Enums\AI\ProviderStatus;
use App\Enums\AI\RunStatus;
use App\Enums\AI\SuggestionStatus;
use App\Models\AI\Agent;
use App\Models\AI\Suggestion;
use App\Models\Inbox\Conversation;
use App\Models\Inbox\Message;
use App\Services\Automation\AutomationExecutionService;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\Data\OutboundMessage;
use App\Services\Customer\CustomerTimelineService;
use App\Services\Developer\OutboundWebhookService;
use App\Services\SaaS\TenantFeatureService;
use App\Services\Sla\SlaService;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Support\Facades\DB;

class AutonomousReplyService
{
    public function __construct(
        private readonly AISettingsService $settings, private readonly TenantFeatureService $features,
        private readonly AIBudgetService $budget, private readonly AIOutputGuardrailService $guardrail,
        private readonly ChannelManager $channels, private readonly CustomerTimelineService $timeline,
        private readonly SlaService $sla, private readonly AutomationExecutionService $automation,
        private readonly OutboundWebhookService $webhooks, private readonly TenantAuditLogService $audit,
    ) {}

    public function eligibleAgent(Conversation $conversation): ?Agent
    {
        $settings = $this->settings->current();
        if (! config('ai.safety.autonomous_replies_enabled') || ! $settings['enabled'] || ! $settings['autonomous_replies_enabled'] || $settings['human_review_required'] || ! $this->features->enabled('ai_autonomous_replies')) return null;
        return Agent::query()->where('status', AgentStatus::Active)->where('deployment_mode', DeploymentMode::Autonomous)
            ->where('auto_reply_enabled', true)->whereHas('channels', fn ($query) => $query->where('channels.id', $conversation->channel_id)->where('ai_agent_channels.enabled', true)->where('ai_agent_channels.deployment_mode', DeploymentMode::Autonomous->value))
            ->with('providerConfig')->first();
    }

    /**
     * Generate and send in one call — the entry point the reply orchestrator
     * uses. This logic previously lived inline in AutoReplyConversationJob,
     * which meant the only way to trigger an agent reply was to dispatch that
     * job; the orchestrator needs to make the same decision synchronously
     * inside its own queued context.
     *
     * Returns false when nothing was sent, so the caller can count it as an
     * AI failure and escalate rather than leaving the customer with silence.
     */
    public function replyWithAgent(Conversation $conversation, Agent $agent): bool
    {
        $result = app(InboxCopilotService::class)->perform($conversation, 'draft', null, [], $agent);
        $suggestion = Suggestion::query()->where('public_uuid', $result['suggestion_uuid'] ?? '')->first();

        if (! $suggestion) {
            return false;
        }

        return $this->send($conversation->fresh(), $agent, $suggestion);
    }

    public function send(Conversation $conversation, Agent $agent, Suggestion $suggestion): bool
    {
        $conversation->loadMissing(['channel.emailSettings','channel.credential','contact','aiInsight','latestMessage']);
        $run = $suggestion->run; $insight = $conversation->aiInsight; $channel = $conversation->channel;
        $guardrail = $this->guardrail->inspectForAutonomousSend((string) $suggestion->text);
        $checks = [
            'platform_enabled' => (bool) config('ai.safety.autonomous_replies_enabled'),
            'workspace_enabled' => (bool) ($this->settings->current()['autonomous_replies_enabled'] ?? false),
            'plan_enabled' => $this->features->enabled('ai_autonomous_replies'),
            'agent_autonomous' => $agent->status === AgentStatus::Active && $agent->deployment_mode === DeploymentMode::Autonomous && $agent->auto_reply_enabled,
            'channel_autonomous' => $channel?->status === 'active' && $channel?->auto_reply_enabled && $agent->channels()->where('channels.id', $channel->id)->wherePivot('enabled', true)->wherePivot('deployment_mode', DeploymentMode::Autonomous->value)->exists(),
            'risk_clear' => $insight && ($insight->risk_flags ?? []) === [] && ! in_array($insight->urgency, ['urgent'], true),
            'approvals_clear' => $run && ! $run->approvals()->where('status', 'pending')->exists(),
            'grounded' => ! $agent->require_citations || ($suggestion->decision_confidence === 'supported' && ($suggestion->citations ?? []) !== []),
            'guardrails_clear' => $guardrail['safe'],
            'provider_healthy' => $agent->providerConfig?->status === ProviderStatus::Healthy,
            'run_successful' => $run?->status === RunStatus::Completed,
            'latest_is_inbound' => $conversation->latestMessage?->direction === 'inbound',
            // Defence in depth. The orchestrator already refuses to generate
            // for a conversation a human owns, but this is the last gate
            // before a message actually reaches a customer, and an agent
            // replying over a human is the failure that damages trust most.
            'control_allows_ai' => $conversation->control_state->allowsAutomatedReply(),
            'recipient_available' => filled($conversation->recipientAddress()),
        ];
        try { $this->budget->ensureAvailable(); $checks['budget_available'] = true; }
        catch (\Throwable) { $checks['budget_available'] = false; }

        $evidence = (array) $suggestion->evidence;
        $evidence['send_decision'] = ['allowed' => ! in_array(false, $checks, true), 'checks' => $checks,
            'guardrail_reasons' => $guardrail['reasons'], 'agent_version_id' => $agent->deployed_version_id, 'decided_at' => now()->toIso8601String()];
        $suggestion->forceFill(['evidence' => $evidence])->save();
        if (in_array(false, $checks, true)) return false;

        $message = DB::transaction(function () use ($conversation, $agent, $suggestion): ?Message {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($locked->messages()->latest()->value('direction') !== 'inbound' || $locked->messages()->where('metadata->ai_suggestion_uuid', $suggestion->public_uuid)->exists()) return null;
            $message = $locked->messages()->create(['sender_type' => 'ai_agent', 'sender_id' => $agent->id, 'sender_name' => $agent->name,
                'direction' => 'outbound', 'message_type' => $locked->channel->type === 'email' ? 'email' : 'text',
                'body' => trim((string) $suggestion->text), 'status' => 'pending', 'sent_at' => now(),
                'metadata' => ['ai_suggestion_uuid' => $suggestion->public_uuid, 'ai_run_uuid' => $suggestion->run?->public_uuid,
                    'agent_uuid' => $agent->public_uuid, 'agent_version_id' => $agent->deployed_version_id]],);
            $locked->update(['last_message_at' => now(), 'message_count' => DB::raw('message_count + 1'),
                'first_response_at' => $locked->first_response_at ?? now(), 'unread_count' => 0]);
            return $message;
        });
        if (! $message) return false;

        $recipient = (string) $conversation->recipientAddress();
        $result = $this->channels->adapter($channel->type)->send($channel, new OutboundMessage($recipient,
            $conversation->subject ?: 'Support conversation', $message->body,
            headers: ['X-PromptBot-Conversation' => $conversation->public_uuid, 'X-PromptBot-AI-Run' => $suggestion->run?->public_uuid]));
        if (! $result->successful) {
            $message->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => $result->error]);
            $suggestion->forceFill(['status' => SuggestionStatus::Failed])->save();
            return false;
        }

        $message->update(['status' => 'sent', 'delivered_at' => now(), 'channel_message_id' => $result->providerMessageId]);
        $suggestion->forceFill(['status' => SuggestionStatus::Sent, 'sent_at' => now()])->save();
        $this->timeline->record('message.outgoing', "Autonomous reply sent by AI agent {$agent->name}.", $conversation->contact, related: $conversation, metadata: ['message_id' => $message->public_uuid, 'ai_run_uuid' => $suggestion->run?->public_uuid]);
        $this->sla->fulfillFirstResponse($conversation); $this->automation->dispatch('message.sent', $conversation->fresh());
        $this->webhooks->record('message.sent', $conversation, ['message_id' => $message->public_uuid, 'sent_by_ai' => true]);
        $this->audit->record('ai.autonomous_reply_sent', description: 'Safety-gated autonomous customer reply sent.', subject: $message,
            newValues: ['agent_uuid' => $agent->public_uuid, 'run_uuid' => $suggestion->run?->public_uuid, 'checks' => $checks], subjectLabel: $conversation->subject ?: $conversation->public_uuid);
        return true;
    }
}
