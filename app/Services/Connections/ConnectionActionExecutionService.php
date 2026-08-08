<?php

namespace App\Services\Connections;

use App\Enums\Connections\ActionRiskLevel;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionAction;
use App\Models\Connections\ConnectionActionExecution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConnectionActionExecutionService
{
    public function __construct(
        private readonly ConnectorManager $connectors,
        private readonly ConnectionActionPolicyService $policy,
        private readonly ConnectionUsageService $usage,
        private readonly ConnectionAuditService $audit,
    ) {}

    public function execute(Connection $connection, ConnectionAction $action, array $input = [], ?User $actor = null, ?string $agentKey = null, ?string $workflowKey = null, ?string $idempotencyKey = null): ConnectionActionExecution
    {
        $this->policy->authorizeExecution($connection, $action, $actor, $agentKey, $workflowKey);
        $started = now();
        $hash = $idempotencyKey ? hash('sha256', tenant('id').'|'.$connection->id.'|'.$action->key.'|'.$idempotencyKey) : null;

        if ($hash && $existing = ConnectionActionExecution::where('idempotency_key_hash', $hash)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($connection, $action, $input, $actor, $agentKey, $workflowKey, $started, $hash): ConnectionActionExecution {
            $approvalRequired = $this->policy->approvalRequired($connection, $action, $agentKey, $workflowKey);
            $execution = ConnectionActionExecution::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'connection_action_id' => $action->id,
                'actor_type' => $actor ? User::class : ($agentKey ? 'agent' : ($workflowKey ? 'workflow' : null)),
                'actor_id' => $actor?->id,
                'agent_key' => $agentKey,
                'workflow_key' => $workflowKey,
                'status' => $approvalRequired ? 'waiting_for_approval' : 'running',
                'risk_level' => $action->risk_level ?? ActionRiskLevel::Low,
                'approval_required' => $approvalRequired,
                'idempotency_key_hash' => $hash,
                'input' => app(SecretRedactor::class)->redact($input),
                'started_at' => $approvalRequired ? null : $started,
            ]);

            if ($approvalRequired) {
                $this->audit->record('action.approval_required', $connection, $actor, message: "Approval required for {$action->name}.", context: ['action' => $action->key], level: 'warning');

                return $execution;
            }

            try {
                $output = $this->connectors->for($connection)->executeAction($connection, $action->key, $input);
                $completed = now();
                $execution->forceFill([
                    'status' => 'completed',
                    'output' => app(SecretRedactor::class)->redact($output),
                    'completed_at' => $completed,
                    'duration_ms' => max(1, $completed->diffInMilliseconds($started)),
                ])->save();

                $this->usage->record('action_execution', connection: $connection, execution: $execution, metadata: ['action' => $action->key, 'risk' => $action->risk_level?->value]);
                $this->audit->record('action.executed', $connection, $actor, message: "Executed {$action->name}.", context: ['action' => $action->key], level: 'info');
            } catch (Throwable $exception) {
                $execution->forceFill([
                    'status' => 'failed',
                    'error_code' => 'ACTION_FAILED',
                    'error_message' => $exception->getMessage(),
                    'completed_at' => now(),
                    'duration_ms' => max(1, now()->diffInMilliseconds($started)),
                ])->save();

                $this->audit->record('action.failed', $connection, $actor, 'failed', $exception->getMessage(), ['action' => $action->key], level: 'error');
            }

            return $execution;
        });
    }
}
