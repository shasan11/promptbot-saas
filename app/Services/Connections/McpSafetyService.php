<?php

namespace App\Services\Connections;

use App\Enums\Connections\ActionRiskLevel;
use App\Enums\Connections\ConnectionType;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionAction;
use App\Models\User;
use InvalidArgumentException;

class McpSafetyService
{
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
    ];

    public function __construct(private readonly ConnectionAuditService $audit) {}

    public function registerTool(Connection $connection, array $payload, ?User $actor = null): ConnectionAction
    {
        $this->validateConnection($connection);

        $key = $this->normaliseKey($payload['key'] ?? '');
        $risk = ActionRiskLevel::from($payload['risk_level'] ?? ActionRiskLevel::Low->value);
        $inputSchema = $this->validateSchema($payload['input_schema'] ?? [], 'input_schema');
        $outputSchema = $this->validateSchema($payload['output_schema'] ?? [], 'output_schema');

        $tool = $connection->actions()->updateOrCreate(
            ['key' => $key],
            [
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $connection->connection_integration_id,
                'name' => trim((string) $payload['name']),
                'description' => $payload['description'] ?? null,
                'action_type' => 'mcp_tool',
                'risk_level' => $risk,
                'requires_approval' => in_array($risk, [ActionRiskLevel::High, ActionRiskLevel::Critical], true),
                'enabled_for_ai' => false,
                'enabled_for_workflows' => false,
                'input_schema' => $inputSchema,
                'output_schema' => $outputSchema,
                'capabilities' => $payload['capabilities'] ?? [],
                'configuration' => [
                    'server_url' => $this->serverUrl($connection),
                    'discovered_at' => now()->toIso8601String(),
                    'discovery_source' => $payload['discovery_source'] ?? 'mcp_discovery',
                ],
                'status' => 'active',
            ],
        );

        $this->audit->record('mcp_tool.discovered', $connection, $actor, message: 'MCP tool discovered.', context: [
            'tool_key' => $tool->key,
            'risk_level' => $risk->value,
            'enabled_for_ai' => false,
            'enabled_for_workflows' => false,
        ]);

        return $tool;
    }

    public function updateToolPolicy(Connection $connection, ConnectionAction $tool, array $payload, ?User $actor = null): ConnectionAction
    {
        $this->validateConnection($connection);
        $this->ensureMcpTool($connection, $tool);

        $risk = $tool->risk_level instanceof ActionRiskLevel ? $tool->risk_level : ActionRiskLevel::from($tool->risk_level);
        $enabledForAi = (bool) ($payload['enabled_for_ai'] ?? false);
        $enabledForWorkflows = (bool) ($payload['enabled_for_workflows'] ?? false);
        $requiresApproval = (bool) ($payload['requires_approval'] ?? $tool->requires_approval);

        if (($enabledForAi || $enabledForWorkflows) && in_array($risk, [ActionRiskLevel::High, ActionRiskLevel::Critical], true) && ! $requiresApproval) {
            throw new InvalidArgumentException('High and critical risk MCP tools require approval before they can be enabled.');
        }

        $before = [
            'enabled_for_ai' => $tool->enabled_for_ai,
            'enabled_for_workflows' => $tool->enabled_for_workflows,
            'requires_approval' => $tool->requires_approval,
        ];

        $tool->forceFill([
            'enabled_for_ai' => $enabledForAi,
            'enabled_for_workflows' => $enabledForWorkflows,
            'requires_approval' => $requiresApproval,
            'status' => $payload['status'] ?? 'active',
        ])->save();

        $enabled = $tool->enabled_for_ai || $tool->enabled_for_workflows;
        $this->audit->record($enabled ? 'mcp_tool.enabled' : 'mcp_tool.disabled', $connection, $actor, message: $enabled ? 'MCP tool enabled.' : 'MCP tool disabled.', context: [
            'tool_key' => $tool->key,
            'risk_level' => $risk->value,
            'before' => $before,
            'after' => [
                'enabled_for_ai' => $tool->enabled_for_ai,
                'enabled_for_workflows' => $tool->enabled_for_workflows,
                'requires_approval' => $tool->requires_approval,
            ],
        ]);

        return $tool;
    }

    private function validateConnection(Connection $connection): void
    {
        if ($connection->connection_type !== ConnectionType::McpServer) {
            throw new InvalidArgumentException('MCP tools can only be registered on MCP server connections.');
        }

        $this->validateServerUrl($this->serverUrl($connection));
    }

    private function ensureMcpTool(Connection $connection, ConnectionAction $tool): void
    {
        if ((int) $tool->connection_id !== (int) $connection->id || $tool->action_type !== 'mcp_tool') {
            throw new InvalidArgumentException('This MCP tool does not belong to the selected connection.');
        }
    }

    private function normaliseKey(string $key): string
    {
        $key = trim($key);

        if (! preg_match('/\A[A-Za-z0-9_.:-]{1,120}\z/', $key)) {
            throw new InvalidArgumentException('MCP tool keys may only contain letters, numbers, dots, colons, underscores, and dashes.');
        }

        return $key;
    }

    private function validateSchema(array $schema, string $field): array
    {
        if ($schema === []) {
            return [];
        }

        if (($schema['type'] ?? null) !== 'object') {
            throw new InvalidArgumentException("{$field} must be an object JSON schema.");
        }

        if (isset($schema['properties']) && ! is_array($schema['properties'])) {
            throw new InvalidArgumentException("{$field}.properties must be an object.");
        }

        return $schema;
    }

    private function serverUrl(Connection $connection): string
    {
        return (string) ($connection->configuration['server_url'] ?? $connection->configuration['base_url'] ?? '');
    }

    private function validateServerUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! $parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new InvalidArgumentException('MCP server URL must be a public HTTPS URL.');
        }

        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            throw new InvalidArgumentException('MCP server URL must not contain embedded credentials.');
        }

        $host = strtolower($parts['host']);

        if (in_array($host, self::BLOCKED_HOSTS, true) || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('MCP server URL host is blocked.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

            if (! filter_var($host, FILTER_VALIDATE_IP, $flags)) {
                throw new InvalidArgumentException('MCP server URL must not point to private or reserved IP ranges.');
            }
        }
    }
}
