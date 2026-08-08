<?php

namespace App\Services\Connections;

use App\Enums\Connections\DataClassification;
use App\Models\Connections\Connection;
use App\Models\User;

class ResourceDiscoveryService
{
    public function __construct(
        private readonly ConnectorManager $connectors,
        private readonly ConnectionAuditService $audit,
    ) {}

    public function discover(Connection $connection, ?User $actor = null): int
    {
        $resources = $this->connectors->for($connection)->discoverResources($connection);

        foreach ($resources as $resource) {
            $connection->resources()->updateOrCreate(
                ['external_id' => $resource['external_id']],
                [
                    'tenant_id' => tenant('id'),
                    'name' => $resource['name'],
                    'resource_type' => $resource['resource_type'],
                    'parent_external_id' => $resource['parent_external_id'] ?? null,
                    'path' => $resource['path'] ?? null,
                    'metadata' => $resource['metadata'] ?? [],
                    'capabilities' => $resource['capabilities'] ?? [],
                    'data_classification' => $resource['data_classification'] ?? DataClassification::Internal,
                    'status' => 'available',
                    'discovered_at' => now(),
                ]
            );
        }

        $this->audit->record('resources.discovered', $connection, $actor, message: count($resources).' resources discovered.');

        return count($resources);
    }
}
