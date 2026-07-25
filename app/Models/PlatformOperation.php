<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformOperation extends Model
{
    use HasUuid;

    protected $fillable = [
        'type',
        'status',
        'progress',
        'requested_by',
        'tenant_id',
        'reason',
        'idempotency_key',
        'started_at',
        'completed_at',
        'failure_message',
        'failure_context',
        'retry_count',
        'logs',
        'metadata',
    ];

    protected $casts = [
        'progress' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failure_context' => 'array',
        'logs' => 'array',
        'metadata' => 'array',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'requested_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
