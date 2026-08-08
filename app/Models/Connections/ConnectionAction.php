<?php

namespace App\Models\Connections;

use App\Enums\Connections\ActionRiskLevel;
use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectionAction extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = ['tenant_id', 'connection_integration_id', 'connection_id', 'key', 'name', 'description', 'action_type', 'risk_level', 'requires_approval', 'enabled_for_ai', 'enabled_for_workflows', 'input_schema', 'output_schema', 'capabilities', 'configuration', 'status'];

    protected function casts(): array
    {
        return [
            'risk_level' => ActionRiskLevel::class,
            'requires_approval' => 'boolean',
            'enabled_for_ai' => 'boolean',
            'enabled_for_workflows' => 'boolean',
            'input_schema' => 'array',
            'output_schema' => 'array',
            'capabilities' => 'array',
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

    public function executions(): HasMany
    {
        return $this->hasMany(ConnectionActionExecution::class);
    }
}
