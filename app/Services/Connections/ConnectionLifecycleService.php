<?php

namespace App\Services\Connections;

use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Enums\Connections\CredentialStatus;
use App\Enums\Connections\SyncStatus;
use App\Models\Connections\Connection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConnectionLifecycleService
{
    public function __construct(private readonly ConnectionAuditService $audit) {}

    public function deletionImpact(Connection $connection): array
    {
        return [
            'data_sources' => $connection->dataSources()->count(),
            'scheduled_syncs' => $connection->dataSources()->whereNotNull('next_sync_at')->count(),
            'running_or_queued_syncs' => $connection->syncRuns()->whereIn('status', [
                SyncStatus::Queued->value,
                SyncStatus::Running->value,
                SyncStatus::Retrying->value,
                SyncStatus::RateLimited->value,
                SyncStatus::WaitingForAuth->value,
            ])->count(),
            'ai_agents' => $connection->agentAccess()->count(),
            'workflows' => $connection->workflowAccess()->count(),
            'access_grants' => $connection->accessGrants()->count(),
            'webhook_endpoints' => $connection->webhookEndpoints()->count(),
            'active_credentials' => $connection->credentials()->where('status', CredentialStatus::Active->value)->count(),
            'actions' => $connection->actions()->where('status', 'active')->count(),
            'triggers' => $connection->triggers()->where('status', 'active')->count(),
            'usage_records' => $connection->usageRecords()->count(),
        ];
    }

    public function deleteSafely(Connection $connection, User $actor, string $confirmation, ?string $reason = null): array
    {
        if ($confirmation !== $connection->name) {
            throw new InvalidArgumentException('Type the connection name to confirm deletion.');
        }

        return DB::transaction(function () use ($connection, $actor, $reason): array {
            $impact = $this->deletionImpact($connection);

            $connection->syncRuns()
                ->whereIn('status', [
                    SyncStatus::Queued->value,
                    SyncStatus::Running->value,
                    SyncStatus::Retrying->value,
                    SyncStatus::RateLimited->value,
                    SyncStatus::WaitingForAuth->value,
                ])
                ->update([
                    'status' => SyncStatus::Cancelled->value,
                    'completed_at' => now(),
                    'error_code' => 'CONNECTION_DELETED',
                    'error_summary' => 'Connection was deleted before the sync completed.',
                ]);

            $connection->dataSources()->update([
                'status' => 'disabled',
                'next_sync_at' => null,
                'updated_at' => now(),
            ]);
            $connection->dataSources()->get()->each->delete();

            $connection->webhookEndpoints()->update([
                'status' => 'disabled',
                'updated_at' => now(),
            ]);
            $connection->webhookEndpoints()->get()->each->delete();

            $connection->actions()->update(['status' => 'disabled', 'updated_at' => now()]);
            $connection->triggers()->update(['status' => 'disabled', 'updated_at' => now()]);
            $connection->agentAccess()->delete();
            $connection->workflowAccess()->delete();
            $connection->accessGrants()->delete();

            $connection->credentials()->update([
                'status' => CredentialStatus::Revoked->value,
                'encrypted_payload' => null,
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ]);

            $connection->forceFill([
                'status' => ConnectionStatus::Archived,
                'health_status' => ConnectionHealth::Disabled,
                'credential_status' => CredentialStatus::Revoked,
                'updated_by' => $actor->id,
                'last_error_at' => now(),
                'last_error_code' => 'CONNECTION_DELETED',
                'last_error_message' => 'Connection deleted by tenant administrator.',
            ])->save();

            $this->audit->record('connection.deleted', $connection, $actor, message: 'Connection deleted safely.', context: [
                'impact' => $impact,
                'reason' => $reason,
                'credentials_scrubbed' => true,
                'webhooks_disabled' => true,
                'syncs_cancelled' => true,
            ], level: 'warning');

            $connection->delete();

            return $impact;
        });
    }
}
