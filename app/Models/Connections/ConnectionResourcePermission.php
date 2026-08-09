<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionResourcePermission extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'connection_resource_id',
        'subject_type',
        'subject_id',
        'capabilities',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ConnectionResource::class, 'connection_resource_id');
    }
}
