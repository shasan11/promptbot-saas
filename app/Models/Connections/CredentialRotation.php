<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialRotation extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = ['tenant_id', 'connection_id', 'connection_credential_id', 'status', 'reason', 'started_at', 'completed_at', 'rotated_by', 'metadata'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ConnectionCredential::class, 'connection_credential_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rotated_by');
    }
}
