<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderRateLimit extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = ['tenant_id', 'connection_id', 'provider', 'bucket', 'limit', 'remaining', 'resets_at', 'backoff_until', 'headers', 'observed_at'];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'resets_at' => 'datetime',
            'backoff_until' => 'datetime',
            'observed_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
