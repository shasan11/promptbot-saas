<?php

namespace App\Models\Connections;

use App\Enums\Connections\ActionRiskLevel;
use App\Models\Connections\Concerns\BelongsToTenant;
use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiOperation extends Model
{
    use BelongsToTenant, HasUuid;

    protected $fillable = ['tenant_id', 'connection_id', 'key', 'name', 'method', 'path', 'headers', 'query_schema', 'body_schema', 'risk_level', 'enabled_for_ai', 'enabled_for_workflows', 'timeout_seconds', 'max_response_kb', 'status'];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'query_schema' => 'array',
            'body_schema' => 'array',
            'risk_level' => ActionRiskLevel::class,
            'enabled_for_ai' => 'boolean',
            'enabled_for_workflows' => 'boolean',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
