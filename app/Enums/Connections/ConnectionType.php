<?php

namespace App\Enums\Connections;

enum ConnectionType: string
{
    case Application = 'application';
    case DataSource = 'data_source';
    case Api = 'api';
    case Database = 'database';
    case Webhook = 'webhook';
    case McpServer = 'mcp_server';
    case FileStorage = 'file_storage';
    case Custom = 'custom';
}
