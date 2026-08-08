<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebhookEndpoint extends Model
{
    use BelongsToTenant, HasUuid, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'name',
        'provider',
        'status',
        'endpoint_path',
        'signature_algorithm',
        'encrypted_secret',
        'event_types',
        'configuration',
        'last_received_at',
    ];

    protected $hidden = ['encrypted_secret'];

    protected function casts(): array
    {
        return [
            'encrypted_secret' => 'encrypted',
            'event_types' => 'array',
            'configuration' => 'array',
            'last_received_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }
}
