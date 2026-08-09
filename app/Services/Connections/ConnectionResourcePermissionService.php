<?php

namespace App\Services\Connections;

use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionResource;
use App\Models\Connections\DataSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ConnectionResourcePermissionService
{
    public function assertDataSourceSyncAllowed(DataSource $dataSource, ?User $actor = null): void
    {
        $resource = $dataSource->resource()->first();

        if (! $resource) {
            return;
        }

        if ((int) $resource->connection_id !== (int) $dataSource->connection_id) {
            throw new InvalidArgumentException('This data source is linked to a resource from another connection.');
        }

        $this->assertResourceSelected($resource);

        if (! $actor || ! $resource->permissions()->exists()) {
            return;
        }

        if (! $this->canUseResource($resource, 'resources.sync', $actor)) {
            throw new InvalidArgumentException('You are not allowed to sync this selected connection resource.');
        }
    }

    public function assertAgentResourceAllowed(Connection $connection, string $resourceKey, string $agentKey): void
    {
        $grant = $connection->agentAccess()->where('agent_key', $agentKey)->first();

        if (! $grant) {
            throw new InvalidArgumentException('This AI agent does not have access to this connection.');
        }

        $resource = $this->findResourceByKey($connection, $resourceKey);
        $this->assertResourceSelected($resource);

        $allowed = array_values(array_filter($grant->allowed_resources ?: []));

        if ($allowed === [] || in_array('*', $allowed, true)) {
            throw new InvalidArgumentException('This AI agent is not allowed to access the selected resource.');
        }

        $resourceAliases = $this->resourceAliases($resource);

        foreach ($allowed as $allowedKey) {
            if (in_array($allowedKey, $resourceAliases, true)) {
                return;
            }
        }

        throw new InvalidArgumentException('This AI agent is not allowed to access the selected resource.');
    }

    public function canUseResource(ConnectionResource $resource, string $capability, ?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        $this->assertResourceSelected($resource);

        return $resource->permissions()
            ->where(function (Builder $query) use ($actor): void {
                $query->where(function (Builder $query): void {
                    $query->where('subject_type', 'workspace')->whereNull('subject_id');
                })->orWhere(function (Builder $query) use ($actor): void {
                    $query->where('subject_type', 'user')->where('subject_id', $actor->id);
                });

                $teamIds = $actor->teams()->pluck('teams.id')->all();
                if ($teamIds !== []) {
                    $query->orWhere(function (Builder $query) use ($teamIds): void {
                        $query->where('subject_type', 'team')->whereIn('subject_id', $teamIds);
                    });
                }

                $roleIds = $actor->roles()->pluck('roles.id')->all();
                if ($roleIds !== []) {
                    $query->orWhere(function (Builder $query) use ($roleIds): void {
                        $query->where('subject_type', 'role')->whereIn('subject_id', $roleIds);
                    });
                }
            })
            ->get()
            ->contains(fn ($grant): bool => in_array($capability, $grant->capabilities ?: [], true));
    }

    public function findResourceByKey(Connection $connection, string $resourceKey): ConnectionResource
    {
        $resource = $connection->resources()
            ->where(function (Builder $query) use ($resourceKey): void {
                $query->where('external_id', $resourceKey)
                    ->orWhere('path', $resourceKey)
                    ->orWhere('uuid', $resourceKey)
                    ->orWhere('name', $resourceKey);
            })
            ->first();

        if (! $resource) {
            throw new InvalidArgumentException('The selected resource is not available on this connection.');
        }

        return $resource;
    }

    private function assertResourceSelected(ConnectionResource $resource): void
    {
        if ($resource->status !== 'available' || ! $resource->selected_at) {
            throw new InvalidArgumentException('This connection resource has not been selected for PromptBot use.');
        }
    }

    private function resourceAliases(ConnectionResource $resource): array
    {
        return array_values(array_unique(array_filter([
            (string) $resource->id,
            (string) $resource->uuid,
            $resource->external_id,
            $resource->path,
            $resource->name,
        ])));
    }
}
