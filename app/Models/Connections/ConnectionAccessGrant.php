<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionAccessGrant extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'connection_id', 'subject_type', 'subject_id', 'capabilities', 'granted_by', 'expires_at'];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
