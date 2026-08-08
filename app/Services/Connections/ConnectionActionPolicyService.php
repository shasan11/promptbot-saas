<?php

namespace App\Services\Connections;

use App\Enums\Connections\ActionRiskLevel;
use App\Enums\Connections\ConnectionStatus;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionAgentAccess;
use App\Models\Connections\ConnectionAction;
use App\Models\Connections\ConnectionWorkflowAccess;
use App\Models\User;
use InvalidArgumentException;

class ConnectionActionPolicyService
{
    public function authorizeExecution(Connection $connection, ConnectionAction $action, ?User $actor = null, ?string $agentKey = null, ?string $workflowKey = null): void
    {
        $this->ensureActionBelongsToConnection($connection, $action);
        $this->ensureTenantContext($connection, $action);

        if ($connection->status !== ConnectionStatus::Active) {
            throw new InvalidArgumentException('This connection is not active.');
        }

        $this->authorizeUser($action, $actor, $agentKey || $workflowKey);

        if ($agentKey && ! $this->authorizeAgent($connection, $action, $agentKey)) {
            throw new InvalidArgumentException('This AI agent is not allowed to execute this connection action.');
        }

        if ($workflowKey && ! $this->authorizeWorkflow($connection, $action, $workflowKey)) {
            throw new InvalidArgumentException('This workflow is not allowed to execute this connection action.');
        }

        if (! $actor && ! $agentKey && ! $workflowKey) {
            throw new InvalidArgumentException('A user, AI agent, or workflow context is required to execute this action.');
        }
    }

    public function authorizeUser(ConnectionAction $action, ?User $actor, bool $contextualExecution = false): void
    {
        if ($actor && ! $actor->can('connections.actions.execute')) {
            throw new InvalidArgumentException('You are not allowed to execute this connection action.');
        }

        if (! $actor && ! $contextualExecution) {
            throw new InvalidArgumentException('You are not allowed to execute this connection action.');
        }

        if ($action->status !== 'active') {
            throw new InvalidArgumentException('This connection action is disabled.');
        }
    }

    public function authorizeAgent(Connection $connection, ConnectionAction $action, string $agentKey): bool
    {
        $grant = $connection->agentAccess()->where('agent_key', $agentKey)->first();

        if (! $grant || ! $action->enabled_for_ai) {
            return false;
        }

        $allowed = $grant->allowed_actions ?: [];

        if (! $this->allowsAction($allowed, $action->key)) {
            return false;
        }

        return ! $grant->read_only || $action->risk_level === ActionRiskLevel::Low;
    }

    public function authorizeWorkflow(Connection $connection, ConnectionAction $action, string $workflowKey): bool
    {
        $grant = $connection->workflowAccess()->where('workflow_key', $workflowKey)->first();

        if (! $grant || ! $action->enabled_for_workflows) {
            return false;
        }

        return $this->allowsAction($grant->allowed_actions ?: [], $action->key);
    }

    public function approvalRequired(Connection $connection, ConnectionAction $action, ?string $agentKey = null, ?string $workflowKey = null): bool
    {
        return $action->requires_approval
            || in_array($action->risk_level, [ActionRiskLevel::High, ActionRiskLevel::Critical], true)
            || $this->agentGrant($connection, $agentKey)?->approval_required
            || $this->workflowGrant($connection, $workflowKey)?->approval_required;
    }

    private function ensureActionBelongsToConnection(Connection $connection, ConnectionAction $action): void
    {
        if ((int) $action->connection_id !== (int) $connection->id) {
            throw new InvalidArgumentException('This action does not belong to the selected connection.');
        }
    }

    private function ensureTenantContext(Connection $connection, ConnectionAction $action): void
    {
        if (! function_exists('tenant') || ! tenancy()->initialized) {
            return;
        }

        $tenantId = (string) tenant('id');

        if ((string) $connection->tenant_id !== $tenantId || (string) $action->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('This action is not available in the current tenant.');
        }
    }

    private function allowsAction(array $allowed, string $actionKey): bool
    {
        return $allowed === ['*'] || in_array($actionKey, $allowed, true);
    }

    private function agentGrant(Connection $connection, ?string $agentKey): ?ConnectionAgentAccess
    {
        return $agentKey ? $connection->agentAccess()->where('agent_key', $agentKey)->first() : null;
    }

    private function workflowGrant(Connection $connection, ?string $workflowKey): ?ConnectionWorkflowAccess
    {
        return $workflowKey ? $connection->workflowAccess()->where('workflow_key', $workflowKey)->first() : null;
    }
}
