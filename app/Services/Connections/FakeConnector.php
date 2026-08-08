<?php

namespace App\Services\Connections;

use App\Contracts\Connections\ConnectorInterface;
use App\Models\Connections\Connection;

class FakeConnector implements ConnectorInterface
{
    public function testConnection(Connection $connection): array
    {
        if ($connection->status->value === 'authentication_required') {
            return ['ok' => false, 'code' => 'AUTHENTICATION_REQUIRED', 'message' => 'Reconnect this account before syncing.'];
        }

        return ['ok' => true, 'code' => 'OK', 'message' => 'Connection test completed successfully.'];
    }

    public function discoverResources(Connection $connection): array
    {
        $provider = $connection->integration?->key ?? 'custom';

        return match ($provider) {
            'google-drive' => [
                ['external_id' => 'drive:customer-support', 'name' => 'Customer Support', 'resource_type' => 'folder', 'path' => '/Customer Support'],
                ['external_id' => 'drive:product-docs', 'name' => 'Product Documentation', 'resource_type' => 'folder', 'path' => '/Product Documentation'],
            ],
            'hubspot' => [
                ['external_id' => 'hubspot:contacts', 'name' => 'Contacts', 'resource_type' => 'crm_object', 'path' => 'CRM / Contacts'],
                ['external_id' => 'hubspot:deals', 'name' => 'Deals', 'resource_type' => 'crm_object', 'path' => 'CRM / Deals'],
            ],
            'postgresql' => [
                ['external_id' => 'public.customer_accounts', 'name' => 'customer_accounts', 'resource_type' => 'database_table', 'path' => 'public.customer_accounts'],
            ],
            'slack' => [
                ['external_id' => 'C_SUPPORT', 'name' => '#support', 'resource_type' => 'channel', 'path' => 'Channels / support'],
            ],
            'github' => [
                ['external_id' => 'repo:product-api-docs', 'name' => 'Product API documentation', 'resource_type' => 'repository', 'path' => 'Engineering / product-api-docs'],
            ],
            default => [
                ['external_id' => 'resource:default', 'name' => 'Default resource', 'resource_type' => 'remote_collection', 'path' => '/'],
            ],
        };
    }

    public function sync(Connection $connection, ?int $dataSourceId = null): array
    {
        return [
            'items_discovered' => 24,
            'items_created' => 3,
            'items_updated' => 7,
            'items_deleted' => 0,
            'items_skipped' => 14,
            'items_failed' => 0,
            'bytes_received' => 262144,
            'api_requests' => 5,
            'cursor_after' => 'cursor_'.now()->timestamp,
        ];
    }

    public function executeAction(Connection $connection, string $actionKey, array $input = []): array
    {
        return [
            'ok' => true,
            'action' => $actionKey,
            'provider' => $connection->integration?->provider,
            'result_id' => 'fake_'.hash('xxh3', $connection->id.$actionKey.json_encode($input)),
            'message' => 'Action executed by the fake connector.',
        ];
    }

    public function capabilities(): array
    {
        return ['connection.test', 'resource.list', 'resource.read', 'sync.full', 'sync.incremental'];
    }
}
