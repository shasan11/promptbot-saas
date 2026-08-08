<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionAgentAccess extends Model
{
    use BelongsToTenant;

    protected $table = 'connection_agent_access';

    protected $fillable = ['tenant_id', 'connection_id', 'agent_key', 'allowed_actions', 'allowed_resources', 'read_only', 'approval_required', 'rate_limit_per_hour'];

    protected function casts(): array
    {
        return [
            'allowed_actions' => 'array',
            'allowed_resources' => 'array',
            'read_only' => 'boolean',
            'approval_required' => 'boolean',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
