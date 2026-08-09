<?php

namespace App\Models\Connections;

use App\Enums\Connections\DataClassification;
use App\Enums\Connections\ResourceType;
use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectionResource extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'external_id',
        'name',
        'resource_type',
        'parent_external_id',
        'path',
        'metadata',
        'capabilities',
        'data_classification',
        'status',
        'discovered_at',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'resource_type' => ResourceType::class,
            'metadata' => 'array',
            'capabilities' => 'array',
            'data_classification' => DataClassification::class,
            'discovered_at' => 'datetime',
            'selected_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function dataSources(): HasMany
    {
        return $this->hasMany(DataSource::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(ConnectionResourcePermission::class);
    }
}
