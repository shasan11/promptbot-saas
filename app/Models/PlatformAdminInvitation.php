<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAdminInvitation extends Model
{
    use HasUuid;

    protected $fillable = [
        'email',
        'role_id',
        'token_hash',
        'invited_by',
        'status',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(PlatformRole::class, 'role_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}
