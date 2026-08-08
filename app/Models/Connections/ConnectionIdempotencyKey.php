<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionIdempotencyKey extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'connection_id', 'key_hash', 'operation', 'status', 'response', 'expires_at'];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
