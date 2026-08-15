<?php

namespace App\Services\AI;

use App\Enums\AI\ApprovalStatus;
use App\Enums\Connections\ActionRiskLevel;
use App\Models\AI\Agent;
use App\Models\AI\ApprovalRequest;
use App\Models\AI\Run;
use App\Models\AI\ToolCall;
use App\Models\Connections\ConnectionAction;
use App\Models\User;
use App\Services\Connections\ConnectionActionExecutionService;
use App\Services\Connections\ConnectionActionPolicyService;
use App\Services\Connections\SecretRedactor;
use Illuminate\Support\Str;
use App\Notifications\AIApprovalRequested;

class AIToolExecutionService
{
    public function __construct(
        private readonly ConnectionActionExecutionService $actions,
        private readonly ConnectionActionPolicyService $policy,
        private readonly SecretRedactor $redactor,
    ) {}

    /** @param array<string, mixed> $arguments */
    public function invoke(Run $run, Agent $agent, ConnectionAction $action, array $arguments, ?User $actor): string
    {
        $risk = $action->risk_level ?? ActionRiskLevel::Low;
        $hash = hash('sha256', $run->id.'|'.$action->id.'|'.json_encode($arguments));
        if ($existing = ToolCall::query()->where('idempotency_key_hash', $hash)->first()) {
            return $existing->status === 'completed' ? ($existing->result_excerpt ?: 'Action already completed.') : "Action state: {$existing->status}.";
        }
        $requiresApproval = $this->requiresApproval($agent, $action, $risk);
        $toolCall = ToolCall::query()->create([
            'ai_run_id' => $run->id, 'agent_id' => $agent->id, 'connection_action_id' => $action->id,
            'tool_key' => $action->key, 'risk_level' => $risk, 'arguments_redacted' => $this->redactor->redact($arguments),
            'argument_hash' => hash('sha256', json_encode($arguments)), 'requires_approval' => $requiresApproval,
            'idempotency_key_hash' => $hash, 'status' => $requiresApproval ? 'waiting_approval' : 'running',
            'started_at' => $requiresApproval ? null : now(),
        ]);
        $run->increment('tool_call_count');

        if ($requiresApproval) {
            $approval = ApprovalRequest::query()->create([
                'ai_run_id' => $run->id, 'agent_id' => $agent->id, 'tool_call_id' => $toolCall->id,
                'connection_action_id' => $action->id, 'risk_level' => $risk, 'requested_action' => $action->name,
                'arguments_redacted' => $this->redactor->redact($arguments),
                'context' => ['tool_key' => $action->key, 'connection_name' => $action->connection?->name],
                'resume_token_encrypted' => json_encode(['arguments' => $arguments]), 'status' => ApprovalStatus::Pending,
                'requested_at' => now(), 'expires_at' => now()->addDay(),
            ]);
            $toolCall->forceFill(['approval_request_id' => $approval->id])->save(); $run->increment('approval_count');
            User::query()->permission('ai.approvals.decide')->each(fn (User $reviewer) => $reviewer->notify(new AIApprovalRequested($approval)));
            return 'Human approval is required before this action can execute. The request is now pending; do not claim the action completed.';
        }

        return $this->execute($toolCall, $agent, $action, $arguments, $actor);
    }

    /** @param array<string, mixed> $arguments */
    public function executeApproved(ApprovalRequest $approval, User $actor): string
    {
        $payload = json_decode((string) $approval->resume_token_encrypted, true) ?: [];
        $toolCall = ToolCall::query()->findOrFail($approval->tool_call_id);
        $action = $approval->action()->with('connection')->firstOrFail();
        return $this->execute($toolCall, $approval->agent, $action, (array) ($payload['arguments'] ?? []), $actor);
    }

    /** @param array<string, mixed> $arguments */
    private function execute(ToolCall $toolCall, Agent $agent, ConnectionAction $action, array $arguments, ?User $actor): string
    {
        $started = now();
        $execution = $this->actions->execute($action->connection, $action, $arguments, $actor, $agent->agent_key, idempotencyKey: $toolCall->idempotency_key_hash);
        $status = $execution->status === 'completed' ? 'completed' : ($execution->status === 'waiting_for_approval' ? 'waiting_approval' : 'failed');
        $result = $status === 'completed' ? json_encode($this->redactor->redact((array) $execution->output), JSON_UNESCAPED_SLASHES) : null;
        $excerpt = $result ? Str::limit($result, 2000) : null;
        $toolCall->forceFill([
            'status' => $status, 'started_at' => $started, 'finished_at' => now(),
            'latency_ms' => max(1, now()->diffInMilliseconds($started)), 'result_excerpt' => $excerpt,
            'error_code' => $status === 'failed' ? 'ACTION_FAILED' : null,
            'error_message_safe' => $status === 'failed' ? 'The connection action failed.' : null,
        ])->save();
        return $status === 'completed' ? ($excerpt ?: 'Action completed successfully.') : ($status === 'waiting_approval' ? 'The connection policy requires an additional approval.' : 'The action failed safely.');
    }

    private function requiresApproval(Agent $agent, ConnectionAction $action, ActionRiskLevel $risk): bool
    {
        if ($agent->human_approval_mode === 'always') return true;
        if ($action->requires_approval || in_array($risk, [ActionRiskLevel::High, ActionRiskLevel::Critical], true)) return true;
        // A connectionless action (schema permits connection_id to be null)
        // has no connection-scoped grant to consult — fail safe by requiring
        // approval rather than crashing or silently allowing it through.
        if (! $action->connection) return true;
        return $this->policy->approvalRequired($action->connection, $action, $agent->agent_key, null);
    }
}
