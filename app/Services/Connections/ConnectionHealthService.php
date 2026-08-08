<?php

namespace App\Services\Connections;

use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionHealthCheck;
use App\Models\User;

class ConnectionHealthService
{
    public function __construct(
        private readonly ConnectorManager $connectors,
        private readonly ConnectionAuditService $audit,
    ) {}

    public function test(Connection $connection, ?User $actor = null): array
    {
        $started = microtime(true);
        $result = $this->connectors->for($connection)->testConnection($connection);
        $ok = (bool) ($result['ok'] ?? false);
        $checkedAt = now();

        $connection->forceFill([
            'status' => $ok ? ConnectionStatus::Active : ConnectionStatus::AuthenticationRequired,
            'health_status' => $ok ? ConnectionHealth::Healthy : ConnectionHealth::AuthenticationExpired,
            'last_checked_at' => $checkedAt,
            'last_successful_check_at' => $ok ? $checkedAt : $connection->last_successful_check_at,
            'last_error_at' => $ok ? null : $checkedAt,
            'last_error_code' => $ok ? null : ($result['code'] ?? 'CONNECTION_FAILED'),
            'last_error_message' => $ok ? null : ($result['message'] ?? 'Connection test failed.'),
        ])->save();

        ConnectionHealthCheck::create([
            'tenant_id' => tenant('id'),
            'connection_id' => $connection->id,
            'status' => $connection->status,
            'health_status' => $connection->health_status,
            'duration_ms' => max(1, (int) round((microtime(true) - $started) * 1000)),
            'error_code' => $ok ? null : ($result['code'] ?? 'CONNECTION_FAILED'),
            'message' => $result['message'] ?? null,
            'result' => app(SecretRedactor::class)->redact($result),
            'checked_at' => $checkedAt,
        ]);

        $this->audit->record('connection.tested', $connection, $actor, $ok ? 'ok' : 'failed', $result['message'] ?? null, ['result' => $result], level: $ok ? 'info' : 'warning');

        return $result;
    }
}
