<?php

namespace App\Models\Connections;

use App\Enums\Connections\SyncStatus;
use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'data_source_id',
        'sync_type',
        'status',
        'started_at',
        'completed_at',
        'duration_ms',
        'cursor_before',
        'cursor_after',
        'items_discovered',
        'items_created',
        'items_updated',
        'items_deleted',
        'items_skipped',
        'items_failed',
        'bytes_received',
        'api_requests',
        'retry_count',
        'error_code',
        'error_summary',
        'triggered_by',
        'trigger_source',
    ];

    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function triggerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function items()
    {
        return $this->hasMany(SyncRunItem::class);
    }
}
