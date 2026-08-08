<?php

namespace App\Models\Connections;

use App\Models\Connections\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectionIntegration extends Model
{
    use HasUuid;

    protected $fillable = [
        'key',
        'name',
        'provider',
        'category',
        'description',
        'logo',
        'auth_methods',
        'capabilities',
        'resource_types',
        'action_definitions',
        'trigger_definitions',
        'configuration_schema',
        'credential_schema',
        'connector_class',
        'connector_version',
        'status',
        'is_beta',
    ];

    protected function casts(): array
    {
        return [
            'auth_methods' => 'array',
            'capabilities' => 'array',
            'resource_types' => 'array',
            'action_definitions' => 'array',
            'trigger_definitions' => 'array',
            'configuration_schema' => 'array',
            'credential_schema' => 'array',
            'is_beta' => 'boolean',
        ];
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class, 'connection_integration_id');
    }
}
