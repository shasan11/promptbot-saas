<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Enums\Connections\ActionRiskLevel;
use App\Http\Controllers\Controller;
use App\Models\Connections\ApiOperation;
use App\Models\Connections\Connection;
use App\Services\Connections\ConnectionAuditService;
use App\Services\Connections\CustomApiSafetyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class ApiOperationController extends Controller
{
    public function store(Request $request, Connection $connection, CustomApiSafetyService $safety, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.api.manage'), 403);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_.:-]*$/i'],
            'name' => ['required', 'string', 'max:255'],
            'method' => ['required', 'string', 'max:10'],
            'path' => ['required', 'string', 'max:1000'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['nullable', 'string', 'max:1000'],
            'query_schema' => ['nullable', 'array'],
            'body_schema' => ['nullable', 'array'],
            'risk_level' => ['required', new Enum(ActionRiskLevel::class)],
            'enabled_for_ai' => ['nullable', 'boolean'],
            'enabled_for_workflows' => ['nullable', 'boolean'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:120'],
            'max_response_kb' => ['required', 'integer', 'min:1', 'max:5120'],
        ]);

        $this->validateSafety($connection, $data, $safety);

        $operation = ApiOperation::updateOrCreate(
            ['connection_id' => $connection->id, 'key' => $data['key']],
            [
                'tenant_id' => tenant('id'),
                'name' => $data['name'],
                'method' => strtoupper($data['method']),
                'path' => $data['path'],
                'headers' => $data['headers'] ?? [],
                'query_schema' => $data['query_schema'] ?? null,
                'body_schema' => $data['body_schema'] ?? null,
                'risk_level' => $data['risk_level'],
                'enabled_for_ai' => $request->boolean('enabled_for_ai'),
                'enabled_for_workflows' => $request->boolean('enabled_for_workflows'),
                'timeout_seconds' => $data['timeout_seconds'],
                'max_response_kb' => $data['max_response_kb'],
                'status' => 'active',
            ]
        );

        $audit->record('api_operation.saved', $connection, $request->user('tenant'), message: 'Custom API operation saved.', context: [
            'operation_id' => $operation->id,
            'key' => $operation->key,
            'method' => $operation->method,
            'path' => $operation->path,
            'risk_level' => $operation->risk_level?->value,
            'enabled_for_ai' => $operation->enabled_for_ai,
            'enabled_for_workflows' => $operation->enabled_for_workflows,
        ]);

        return back()->with('status', 'API operation saved.');
    }

    public function destroy(Request $request, ApiOperation $operation, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.api.manage'), 403);

        $operation->load('connection');
        $connection = $operation->connection;

        $operation->forceFill(['status' => 'disabled'])->save();
        $audit->record('api_operation.disabled', $connection, $request->user('tenant'), message: 'Custom API operation disabled.', context: [
            'operation_id' => $operation->id,
            'key' => $operation->key,
        ], level: 'warning');

        return back()->with('status', 'API operation disabled.');
    }

    private function validateSafety(Connection $connection, array $data, CustomApiSafetyService $safety): void
    {
        try {
            $baseUrl = $connection->configuration['base_url'] ?? null;

            if (! is_string($baseUrl) || trim($baseUrl) === '') {
                throw new \InvalidArgumentException('Set a safe HTTPS base URL in the connection configuration before adding API operations.');
            }

            $safety->validateBaseUrl($baseUrl);
            $safety->validateOperation($data['method'], $data['path'], $data['risk_level']);
            $safety->validateHeaders($data['headers'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['path' => $exception->getMessage()]);
        }
    }
}
