<?php

namespace App\Models\Connections;

use App\Enums\Connections\ActionRiskLevel;
use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionActionExecution extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = ['tenant_id', 'connection_id', 'connection_action_id', 'actor_type', 'actor_id', 'agent_key', 'workflow_key', 'status', 'risk_level', 'approval_required', 'idempotency_key_hash', 'input', 'output', 'error_code', 'error_message', 'started_at', 'completed_at', 'duration_ms'];

    protected function casts(): array
    {
        return [
            'risk_level' => ActionRiskLevel::class,
            'approval_required' => 'boolean',
            'input' => 'array',
            'output' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(ConnectionAction::class, 'connection_action_id');
    }
}
