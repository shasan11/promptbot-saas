<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionTrigger extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = ['tenant_id', 'connection_integration_id', 'connection_id', 'key', 'name', 'description', 'trigger_type', 'event_schema', 'configuration', 'status'];

    protected function casts(): array
    {
        return [
            'event_schema' => 'array',
            'configuration' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ConnectionIntegration::class, 'connection_integration_id');
    }
}
