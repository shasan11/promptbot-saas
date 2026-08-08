<?php

namespace App\Models\Connections;

use App\Enums\Connections\ResourceType;
use App\Enums\Connections\SyncMode;
use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataSource extends Model
{
    use BelongsToTenant, HasUuid, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'connection_resource_id',
        'name',
        'description',
        'resource_type',
        'usage',
        'configuration',
        'status',
        'sync_mode',
        'sync_schedule',
        'last_synced_at',
        'last_successful_sync_at',
        'next_sync_at',
        'last_cursor',
        'records_synced',
        'bytes_synced',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'resource_type' => ResourceType::class,
            'usage' => 'array',
            'configuration' => 'array',
            'sync_mode' => SyncMode::class,
            'last_synced_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
            'next_sync_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ConnectionResource::class, 'connection_resource_id');
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function databaseConfig()
    {
        return $this->hasOne(DatabaseDataSourceConfig::class);
    }
}
