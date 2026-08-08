<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionLog extends Model
{
    use BelongsToTenant, HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'data_source_id',
        'sync_run_id',
        'level',
        'event',
        'status',
        'message',
        'actor_type',
        'actor_id',
        'context',
        'error_code',
        'correlation_id',
        'request_id',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }
}
