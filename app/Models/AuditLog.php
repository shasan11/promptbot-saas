<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'administrator_id',
        'tenant_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'request_uuid',
        'impersonation_session_id',
        'severity',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'administrator_id');
    }
}
