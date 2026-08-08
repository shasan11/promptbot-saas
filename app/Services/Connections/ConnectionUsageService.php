<?php

namespace App\Services\Connections;

use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionActionExecution;
use App\Models\Connections\ConnectionUsageRecord;
use App\Models\Connections\DataSource;

class ConnectionUsageService
{
    public function record(
        string $usageType,
        int $quantity = 1,
        string $unit = 'count',
        int $bytes = 0,
        ?Connection $connection = null,
        ?DataSource $dataSource = null,
        ?ConnectionActionExecution $execution = null,
        array $metadata = [],
    ): ConnectionUsageRecord {
        return ConnectionUsageRecord::create([
            'tenant_id' => tenant('id'),
            'connection_id' => $connection?->id,
            'data_source_id' => $dataSource?->id,
            'connection_action_execution_id' => $execution?->id,
            'usage_type' => $usageType,
            'quantity' => $quantity,
            'unit' => $unit,
            'bytes' => $bytes,
            'metadata' => app(SecretRedactor::class)->redact($metadata),
            'usage_date' => today(),
            'created_at' => now(),
        ]);
    }
}
