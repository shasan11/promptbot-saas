<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    use HasUuid;
    use UsesCentralConnection;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'ai_provider_id',
        'provider_driver',
        'provider_name',
        'ai_model_id',
        'model_key',
        'purpose',
        'capability',
        'status',
        'error_code',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
        'latency_ms',
        'request_uuid',
        'created_at',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost' => 'decimal:6',
        'latency_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->created_at ??= now();
        });
    }
}
