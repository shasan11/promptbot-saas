<?php

namespace App\Models\Connections;

use App\Enums\Connections\AuthenticationType;
use App\Enums\Connections\CredentialStatus;
use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionCredential extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'type',
        'status',
        'encrypted_payload',
        'masked_secret',
        'metadata',
        'expires_at',
        'refresh_expires_at',
        'last_used_at',
        'rotated_at',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['encrypted_payload'];

    protected function casts(): array
    {
        return [
            'type' => AuthenticationType::class,
            'status' => CredentialStatus::class,
            'encrypted_payload' => 'encrypted:array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'rotated_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
