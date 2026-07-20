<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAdminLoginAttempt extends Model
{
    use HasUuid;

    protected $fillable = [
        'administrator_id',
        'email',
        'successful',
        'ip_address',
        'user_agent',
        'failure_reason',
        'attempted_at',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'attempted_at' => 'datetime',
    ];

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'administrator_id');
    }
}
