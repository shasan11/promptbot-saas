<?php

namespace App\Contracts\Connections;

use App\Models\Connections\Connection;

interface ConnectorInterface
{
    public function testConnection(Connection $connection): array;

    public function discoverResources(Connection $connection): array;

    public function sync(Connection $connection, ?int $dataSourceId = null): array;

    public function executeAction(Connection $connection, string $actionKey, array $input = []): array;

    public function capabilities(): array;
}
