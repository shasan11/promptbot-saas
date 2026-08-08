<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthAuthorizationState extends Model
{
    use BelongsToTenant, HasUuid;

    protected $table = 'oauth_authorization_states';

    protected $fillable = [
        'tenant_id',
        'connection_integration_id',
        'connection_id',
        'state_hash',
        'code_verifier',
        'code_challenge',
        'scopes',
        'redirect_path',
        'metadata',
        'authorized_by',
        'ip_address',
        'user_agent',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'code_verifier' => 'encrypted',
            'scopes' => 'array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ConnectionIntegration::class, 'connection_integration_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
