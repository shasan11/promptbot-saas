<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDeliveryAttempt extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'webhook_event_id', 'attempt', 'status', 'response_status', 'latency_ms', 'error_message', 'attempted_at'];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }
}
