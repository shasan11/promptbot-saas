<?php

namespace App\Services\Connections;

use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionIdempotencyKey;
use Illuminate\Support\Facades\Hash;

class IdempotencyService
{
    public function start(string $operation, string $key, ?Connection $connection = null, int $ttlMinutes = 1440): ConnectionIdempotencyKey
    {
        return ConnectionIdempotencyKey::firstOrCreate(
            ['key_hash' => hash('sha256', tenant('id').'|'.$operation.'|'.$key)],
            [
                'tenant_id' => tenant('id'),
                'connection_id' => $connection?->id,
                'operation' => $operation,
                'status' => 'started',
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]
        );
    }

    public function complete(ConnectionIdempotencyKey $key, array $response = []): void
    {
        $key->forceFill(['status' => 'completed', 'response' => $response])->save();
    }
}
