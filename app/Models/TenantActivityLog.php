<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantActivityLog extends Model
{
    protected $table = 'activity_logs';

    public $timestamps = true;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'actor_name', 'event', 'subject_type', 'subject_id', 'subject_label',
        'description', 'old_values', 'new_values', 'properties', 'ip_address', 'user_agent', 'request_id',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'properties' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
