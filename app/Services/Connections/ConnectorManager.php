<?php

namespace App\Services\Connections;

use App\Contracts\Connections\ConnectorInterface;
use App\Models\Connections\Connection;

class ConnectorManager
{
    public function for(Connection $connection): ConnectorInterface
    {
        $class = $connection->integration?->connector_class;

        if ($class && is_a($class, ConnectorInterface::class, true)) {
            return app($class);
        }

        return app(FakeConnector::class);
    }
}
