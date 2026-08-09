<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Enums\Connections\ResourceType;
use App\Http\Controllers\Controller;
use App\Models\Connections\DataSource;
use App\Services\Connections\ConnectionAuditService;
use App\Services\Connections\DatabaseSafetyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DatabaseConfigController extends Controller
{
    public function store(Request $request, DataSource $dataSource, DatabaseSafetyService $safety, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.databases.manage'), 403);
        abort_unless(in_array($dataSource->resource_type, [ResourceType::DatabaseTable, ResourceType::DatabaseView], true), 404);

        $data = $request->validate([
            'schema_name' => ['nullable', 'string', 'max:120'],
            'table_name' => ['required', 'string', 'max:180'],
            'primary_key' => ['nullable', 'string', 'max:120'],
            'incremental_column' => ['nullable', 'string', 'max:120'],
            'allowed_columns' => ['required', 'array', 'min:1'],
            'allowed_columns.*' => ['required', 'string', 'max:120'],
            'excluded_columns' => ['nullable', 'array'],
            'excluded_columns.*' => ['string', 'max:120'],
            'filters' => ['nullable', 'array'],
            'row_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'read_only' => ['required', 'boolean'],
            'raw_sql' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $normalized = $safety->validateDataSourceConfig($data);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['allowed_columns' => $exception->getMessage()]);
        }

        $dataSource->load('connection');

        if (($dataSource->connection->configuration['host'] ?? null) && is_string($dataSource->connection->configuration['host'])) {
            try {
                $safety->validateDestination($dataSource->connection->configuration['host']);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['table_name' => $exception->getMessage()]);
            }
        }

        $config = $dataSource->databaseConfig()->updateOrCreate(
            ['data_source_id' => $dataSource->id],
            [
                'tenant_id' => tenant('id'),
                'schema_name' => $data['schema_name'] ?? null,
                'table_name' => $data['table_name'],
                'primary_key' => $data['primary_key'] ?? null,
                'incremental_column' => $data['incremental_column'] ?? null,
                'allowed_columns' => $normalized['allowed_columns'],
                'excluded_columns' => $normalized['excluded_columns'],
                'filters' => $data['filters'] ?? [],
                'row_limit' => $data['row_limit'],
                'read_only' => (bool) $data['read_only'],
                'raw_sql' => $data['raw_sql'] ?? null,
                'validated_at' => now(),
            ]
        );

        $audit->record('database.query_configuration_changed', $dataSource->connection, $request->user('tenant'), message: 'Database data source configuration saved.', context: [
            'data_source_id' => $dataSource->id,
            'database_config_id' => $config->id,
            'schema_name' => $config->schema_name,
            'table_name' => $config->table_name,
            'allowed_columns' => $config->allowed_columns,
            'excluded_columns' => $config->excluded_columns,
            'row_limit' => $config->row_limit,
            'read_only' => $config->read_only,
            'raw_sql_enabled' => filled($config->raw_sql),
            'sensitive_columns_detected' => $normalized['sensitive_columns'],
        ], dataSource: $dataSource);

        return back()->with('status', 'Database source configuration saved.');
    }
}
