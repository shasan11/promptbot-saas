<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantInvitation extends Model
{
    protected $fillable = [
        'email', 'name', 'job_title', 'token_hash', 'role_ids', 'team_ids', 'department_id',
        'locale', 'timezone', 'message', 'invited_by', 'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'role_ids' => 'array',
            'team_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public static function generateToken(): array
    {
        $plain = Str::random(48);

        return [$plain, hash('sha256', $plain)];
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at?->isPast();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
