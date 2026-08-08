<?php

namespace App\Models\Connections;

use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionHealthCheck extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = ['tenant_id', 'connection_id', 'status', 'health_status', 'duration_ms', 'error_code', 'message', 'result', 'checked_at'];

    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
            'health_status' => ConnectionHealth::class,
            'result' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
