<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEvent extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = [
        'tenant_id',
        'webhook_endpoint_id',
        'connection_id',
        'provider_event_id',
        'event_type',
        'status',
        'http_method',
        'headers',
        'payload',
        'payload_hash',
        'payload_size',
        'signature',
        'received_at',
        'processed_at',
        'response_status',
        'latency_ms',
        'replayed_at',
        'replayed_by',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'replayed_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(WebhookDeliveryAttempt::class);
    }
}
