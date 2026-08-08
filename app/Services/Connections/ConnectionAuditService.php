<?php

namespace App\Services\Connections;

use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionLog;
use App\Models\Connections\DataSource;
use App\Models\Connections\SyncRun;
use App\Models\User;
use Illuminate\Support\Str;

class ConnectionAuditService
{
    public function __construct(private readonly SecretRedactor $redactor) {}

    public function record(
        string $event,
        ?Connection $connection = null,
        ?User $actor = null,
        string $status = 'ok',
        ?string $message = null,
        array $context = [],
        ?DataSource $dataSource = null,
        ?SyncRun $syncRun = null,
        string $level = 'info',
    ): ConnectionLog {
        return ConnectionLog::create([
            'tenant_id' => tenant('id'),
            'connection_id' => $connection?->id,
            'data_source_id' => $dataSource?->id,
            'sync_run_id' => $syncRun?->id,
            'level' => $level,
            'event' => $event,
            'status' => $status,
            'message' => $message,
            'actor_type' => $actor ? User::class : null,
            'actor_id' => $actor?->id,
            'context' => $this->redactor->redact($context),
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }
}
