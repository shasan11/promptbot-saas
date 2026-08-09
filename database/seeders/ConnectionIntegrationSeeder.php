<?php

namespace Database\Seeders;

use App\Enums\Connections\AuthenticationType;
use App\Enums\Connections\ConnectionCapability;
use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Enums\Connections\ConnectionType;
use App\Enums\Connections\CredentialStatus;
use App\Enums\Connections\Environment;
use App\Enums\Connections\ResourceType;
use App\Enums\Connections\SyncMode;
use App\Enums\Connections\SyncStatus;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionCredential;
use App\Models\Connections\ConnectionHealthCheck;
use App\Models\Connections\ConnectionIntegration;
use App\Models\Connections\ConnectionLog;
use App\Models\Connections\ConnectionAction;
use App\Models\Connections\ConnectionAgentAccess;
use App\Models\Connections\ConnectionTrigger;
use App\Models\Connections\ConnectionUsageRecord;
use App\Models\Connections\ConnectionWorkflowAccess;
use App\Models\Connections\ApiOperation;
use App\Models\Connections\DatabaseDataSourceConfig;
use App\Models\Connections\DataSource;
use App\Models\Connections\ProviderRateLimit;
use App\Models\Connections\SyncRun;
use App\Models\Connections\SyncRunItem;
use App\Models\Connections\WebhookEndpoint;
use App\Models\Connections\WebhookDeliveryAttempt;
use App\Models\Connections\WebhookEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ConnectionIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $integrations = collect($this->integrations())->mapWithKeys(function (array $definition) {
            $integration = ConnectionIntegration::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );

            return [$integration->key => $integration];
        });

        $actor = User::query()->orderBy('id')->first();

        if (Connection::query()->exists()) {
            Connection::query()->with('integration')->get()->each(function (Connection $connection) use ($actor): void {
                $this->ensureCredential($connection, $actor);
                $this->ensureOperationalSamples($connection);
                $this->ensureActionSamples($connection);
            });
            $this->ensureWebhookSample($actor);

            return;
        }

        $samples = [
            [
                'integration' => 'google-drive',
                'name' => 'Google Drive — Acme Workspace',
                'type' => ConnectionType::FileStorage,
                'auth' => AuthenticationType::OAuth2,
                'account' => 'docs@acme.test',
                'usage' => ['knowledge_base', 'search', 'data_synchronization'],
                'source' => ['Customer Support Drive Folder', ResourceType::Folder, 'Customer Support', SyncMode::Incremental, 'every_6_hours', 2384],
                'status' => ConnectionStatus::Active,
                'health' => ConnectionHealth::Healthy,
            ],
            [
                'integration' => 'hubspot',
                'name' => 'HubSpot — Sales CRM',
                'type' => ConnectionType::Application,
                'auth' => AuthenticationType::OAuth2,
                'account' => 'Acme Sales',
                'usage' => ['ai_agent_tool', 'workflow_automation', 'analytics'],
                'source' => ['HubSpot Contacts', ResourceType::CrmObject, 'Contacts', SyncMode::Incremental, 'hourly', 9180],
                'status' => ConnectionStatus::Degraded,
                'health' => ConnectionHealth::RateLimited,
                'error' => ['RATE_LIMITED', 'Provider rate limit reached. Sync will resume after reset.'],
            ],
            [
                'integration' => 'postgresql',
                'name' => 'PostgreSQL — Reporting Replica',
                'type' => ConnectionType::Database,
                'auth' => AuthenticationType::DatabaseCredentials,
                'account' => 'reporting-replica.acme.test',
                'usage' => ['analytics', 'data_synchronization'],
                'source' => ['customer_accounts database table', ResourceType::DatabaseTable, 'public.customer_accounts', SyncMode::Scheduled, 'daily', 42000],
                'status' => ConnectionStatus::Active,
                'health' => ConnectionHealth::Healthy,
                'environment' => Environment::Production,
            ],
            [
                'integration' => 'slack',
                'name' => 'Slack — Customer Support',
                'type' => ConnectionType::Application,
                'auth' => AuthenticationType::OAuth2,
                'account' => 'Acme Support',
                'usage' => ['inbox_channel', 'workflow_automation', 'search'],
                'source' => ['#support Slack channel', ResourceType::Channel, '#support', SyncMode::Webhook, 'webhook_based', 1240],
                'status' => ConnectionStatus::Active,
                'health' => ConnectionHealth::Healthy,
            ],
            [
                'integration' => 'github',
                'name' => 'GitHub — Engineering',
                'type' => ConnectionType::Application,
                'auth' => AuthenticationType::OAuth2,
                'account' => 'acme-engineering',
                'usage' => ['knowledge_base', 'workflow_automation', 'ai_agent_tool'],
                'source' => ['Product API documentation repository', ResourceType::Repository, 'product-api-docs', SyncMode::Incremental, 'every_12_hours', 640],
                'status' => ConnectionStatus::AuthenticationRequired,
                'health' => ConnectionHealth::AuthenticationExpired,
                'error' => ['AUTHENTICATION_REQUIRED', 'OAuth refresh token was revoked. Reconnect to continue syncing.'],
            ],
            [
                'integration' => 'custom-rest',
                'name' => 'Custom REST — Shipping API',
                'type' => ConnectionType::Api,
                'auth' => AuthenticationType::ApiKey,
                'account' => 'shipping-api.acme.test',
                'usage' => ['workflow_automation', 'outbound_action'],
                'source' => ['Shipping API orders endpoint', ResourceType::ApiEndpoint, '/orders', SyncMode::Manual, 'manual', 86],
                'status' => ConnectionStatus::Active,
                'health' => ConnectionHealth::Healthy,
            ],
        ];

        foreach ($samples as $sample) {
            $connection = Connection::create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $integrations[$sample['integration']]->id,
                'name' => $sample['name'],
                'status' => $sample['status'],
                'health_status' => $sample['health'],
                'connection_type' => $sample['type'],
                'auth_type' => $sample['auth'],
                'environment' => $sample['environment'] ?? Environment::Sandbox,
                'provider_account_name' => $sample['account'],
                'usage' => $sample['usage'],
                'configuration' => ['read_only' => true, 'least_privilege' => true],
                'credential_status' => $sample['status'] === ConnectionStatus::AuthenticationRequired ? CredentialStatus::Expired : CredentialStatus::Active,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
                'owner_user_id' => $actor?->id,
                'connected_at' => now()->subDays(rand(2, 25)),
                'last_checked_at' => now()->subMinutes(rand(10, 180)),
                'last_successful_check_at' => $sample['health'] === ConnectionHealth::Healthy ? now()->subMinutes(rand(10, 120)) : now()->subDays(1),
                'last_error_at' => isset($sample['error']) ? now()->subMinutes(rand(15, 240)) : null,
                'last_error_code' => $sample['error'][0] ?? null,
                'last_error_message' => $sample['error'][1] ?? null,
            ]);

            $this->ensureCredential($connection, $actor);

            [$sourceName, $type, $path, $mode, $schedule, $records] = $sample['source'];

            $resource = $connection->resources()->create([
                'tenant_id' => tenant('id'),
                'external_id' => str($path)->slug(':')->toString(),
                'name' => $path,
                'resource_type' => $type,
                'path' => $path,
                'metadata' => ['sample' => true],
                'capabilities' => ['resource.read', 'resource.search'],
                'selected_at' => now()->subDays(2),
                'discovered_at' => now()->subDays(3),
            ]);

            $dataSource = DataSource::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'connection_resource_id' => $resource->id,
                'name' => $sourceName,
                'resource_type' => $type,
                'usage' => $sample['usage'],
                'configuration' => ['deletion_policy' => 'mark_unavailable', 'knowledge_boundary' => true],
                'status' => $sample['status'] === ConnectionStatus::AuthenticationRequired ? 'paused' : 'active',
                'sync_mode' => $mode,
                'sync_schedule' => $schedule,
                'last_synced_at' => now()->subHours(rand(2, 30)),
                'last_successful_sync_at' => $sample['health'] === ConnectionHealth::Healthy ? now()->subHours(rand(2, 30)) : now()->subDays(2),
                'next_sync_at' => $mode === SyncMode::Manual ? null : now()->addHours(rand(1, 10)),
                'last_cursor' => 'seed_cursor_'.$connection->id,
                'records_synced' => $records,
                'bytes_synced' => $records * 512,
                'created_by' => $actor?->id,
            ]);

            SyncRun::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'data_source_id' => $dataSource->id,
                'sync_type' => $mode->value,
                'status' => $sample['health'] === ConnectionHealth::Healthy ? SyncStatus::Completed : SyncStatus::Failed,
                'started_at' => now()->subHours(4),
                'completed_at' => now()->subHours(4)->addMinutes(3),
                'duration_ms' => 180000,
                'items_discovered' => 42,
                'items_created' => 8,
                'items_updated' => 18,
                'items_skipped' => 16,
                'items_failed' => $sample['health'] === ConnectionHealth::Healthy ? 0 : 3,
                'bytes_received' => 320000,
                'api_requests' => 9,
                'error_code' => $sample['error'][0] ?? null,
                'error_summary' => $sample['error'][1] ?? null,
                'triggered_by' => $actor?->id,
                'trigger_source' => 'scheduled',
            ]);

            $this->ensureOperationalSamples($connection);
            $this->ensureActionSamples($connection);

            ConnectionLog::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'data_source_id' => $dataSource->id,
                'event' => $sample['health'] === ConnectionHealth::Healthy ? 'sync.completed' : 'connection.needs_attention',
                'status' => $sample['health'] === ConnectionHealth::Healthy ? 'ok' : 'failed',
                'level' => $sample['health'] === ConnectionHealth::Healthy ? 'info' : 'warning',
                'message' => $sample['health'] === ConnectionHealth::Healthy ? $connection->name.' synchronized successfully.' : $sample['error'][1],
                'context' => ['provider' => $integrations[$sample['integration']]->provider],
                'created_at' => now()->subMinutes(rand(5, 240)),
            ]);
        }

        $this->ensureWebhookSample($actor);
    }

    private function integrations(): array
    {
        return [
            $this->integration('google-drive', 'Google Drive', 'Google', 'Storage', 'Connect folders, shared drives, documents, sheets, and file metadata.', [AuthenticationType::OAuth2], [ConnectionCapability::ResourceList, ConnectionCapability::ResourceRead, ConnectionCapability::ResourceSearch, ConnectionCapability::SyncFull, ConnectionCapability::SyncIncremental, ConnectionCapability::FilesDownload, ConnectionCapability::WebhookReceive], [ResourceType::Folder, ResourceType::File, ResourceType::Sheet]),
            $this->integration('hubspot', 'HubSpot', 'HubSpot', 'CRM', 'Connect contacts, companies, tickets, deals, and CRM activities.', [AuthenticationType::OAuth2, AuthenticationType::ApiKey], [ConnectionCapability::ResourceList, ConnectionCapability::ResourceRead, ConnectionCapability::ResourceSearch, ConnectionCapability::ResourceCreate, ConnectionCapability::ResourceUpdate, ConnectionCapability::SyncIncremental, ConnectionCapability::WebhookReceive, ConnectionCapability::ActionsExecute], [ResourceType::CrmObject]),
            $this->integration('postgresql', 'PostgreSQL', 'PostgreSQL', 'Databases', 'Connect read-only replicas, schemas, tables, and analytics data sources.', [AuthenticationType::DatabaseCredentials, AuthenticationType::SshKey], [ConnectionCapability::Test, ConnectionCapability::ResourceList, ConnectionCapability::RecordsQuery, ConnectionCapability::SyncFull, ConnectionCapability::SyncIncremental], [ResourceType::DatabaseTable, ResourceType::DatabaseView]),
            $this->integration('slack', 'Slack', 'Slack', 'Communication', 'Connect channels, messages, users, and workflow triggers.', [AuthenticationType::OAuth2], [ConnectionCapability::ResourceList, ConnectionCapability::ResourceRead, ConnectionCapability::ResourceSearch, ConnectionCapability::MessagesSend, ConnectionCapability::WebhookReceive, ConnectionCapability::ActionsExecute], [ResourceType::Channel]),
            $this->integration('github', 'GitHub', 'GitHub', 'Developer Tools', 'Connect organizations, repositories, issues, pull requests, and documentation.', [AuthenticationType::OAuth2, AuthenticationType::ApiKey], [ConnectionCapability::ResourceList, ConnectionCapability::ResourceRead, ConnectionCapability::ResourceSearch, ConnectionCapability::SyncIncremental, ConnectionCapability::WebhookReceive, ConnectionCapability::ActionsExecute], [ResourceType::Repository]),
            $this->integration('custom-rest', 'Custom REST API', 'Custom', 'Custom', 'Build secure custom API connections with scoped endpoints, headers, tests, and actions.', [AuthenticationType::ApiKey, AuthenticationType::BearerToken, AuthenticationType::Basic, AuthenticationType::CustomHeaders, AuthenticationType::None], [ConnectionCapability::Test, ConnectionCapability::ResourceRead, ConnectionCapability::ResourceCreate, ConnectionCapability::ResourceUpdate, ConnectionCapability::ActionsExecute], [ResourceType::ApiEndpoint, ResourceType::RemoteCollection]),
            $this->integration('notion', 'Notion', 'Notion', 'Knowledge', 'Connect pages, databases, workspaces, and knowledge content.', [AuthenticationType::OAuth2], [ConnectionCapability::ResourceList, ConnectionCapability::ResourceRead, ConnectionCapability::ResourceSearch, ConnectionCapability::SyncIncremental], [ResourceType::DocumentCollection]),
            $this->integration('mcp-server', 'MCP Server', 'MCP', 'AI', 'Register MCP servers and expose approved tools and resources to AI agents.', [AuthenticationType::BearerToken, AuthenticationType::None], [ConnectionCapability::Test, ConnectionCapability::ResourceList, ConnectionCapability::AiTool, ConnectionCapability::ActionsExecute], [ResourceType::RemoteCollection]),
        ];
    }

    private function integration(string $key, string $name, string $provider, string $category, string $description, array $auth, array $capabilities, array $resources): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'provider' => $provider,
            'category' => $category,
            'description' => $description,
            'auth_methods' => array_map(fn ($type) => $type->value, $auth),
            'capabilities' => array_map(fn ($capability) => $capability->value, $capabilities),
            'resource_types' => array_map(fn ($resource) => $resource->value, $resources),
            'action_definitions' => [],
            'trigger_definitions' => [],
            'configuration_schema' => ['usage' => ['knowledge_base', 'ai_agent_tool', 'workflow_automation', 'search', 'analytics']],
            'credential_schema' => $this->credentialSchema($key, $auth),
            'connector_class' => null,
            'connector_version' => 'v1',
            'status' => 'available',
            'is_beta' => in_array($key, ['mcp-server'], true),
        ];
    }

    private function credentialSchema(string $key, array $auth): array
    {
        $schema = ['auth_methods' => array_map(fn ($type) => $type->value, $auth)];

        $oauth = match ($key) {
            'google-drive' => [
                'default_scopes' => ['https://www.googleapis.com/auth/drive.readonly'],
                'allowed_scopes' => [
                    'https://www.googleapis.com/auth/drive.readonly',
                    'https://www.googleapis.com/auth/drive.metadata.readonly',
                    'https://www.googleapis.com/auth/spreadsheets.readonly',
                ],
                'required_scopes_by_usage' => [
                    'knowledge_base' => ['https://www.googleapis.com/auth/drive.readonly'],
                    'search' => ['https://www.googleapis.com/auth/drive.metadata.readonly'],
                    'analytics' => ['https://www.googleapis.com/auth/spreadsheets.readonly'],
                ],
                'scope_descriptions' => [
                    'https://www.googleapis.com/auth/drive.readonly' => 'Read files selected for knowledge and sync.',
                    'https://www.googleapis.com/auth/drive.metadata.readonly' => 'Read file and folder metadata for discovery and search.',
                    'https://www.googleapis.com/auth/spreadsheets.readonly' => 'Read selected Google Sheets data.',
                ],
            ],
            'hubspot' => [
                'default_scopes' => ['crm.objects.contacts.read', 'crm.objects.companies.read'],
                'allowed_scopes' => [
                    'crm.objects.contacts.read',
                    'crm.objects.companies.read',
                    'crm.objects.deals.read',
                    'crm.objects.tickets.read',
                    'crm.objects.contacts.write',
                    'crm.objects.companies.write',
                ],
                'required_scopes_by_usage' => [
                    'customer_data' => ['crm.objects.contacts.read', 'crm.objects.companies.read'],
                    'analytics' => ['crm.objects.deals.read'],
                    'workflow_automation' => ['crm.objects.contacts.write'],
                ],
                'scope_descriptions' => [
                    'crm.objects.contacts.read' => 'Read contacts selected for CRM sync.',
                    'crm.objects.companies.read' => 'Read companies selected for CRM sync.',
                    'crm.objects.deals.read' => 'Read deal data for analytics.',
                    'crm.objects.tickets.read' => 'Read HubSpot tickets.',
                    'crm.objects.contacts.write' => 'Create or update contacts from approved workflows.',
                    'crm.objects.companies.write' => 'Create or update companies from approved workflows.',
                ],
            ],
            'slack' => [
                'default_scopes' => ['channels:read', 'channels:history'],
                'allowed_scopes' => ['channels:read', 'channels:history', 'groups:read', 'groups:history', 'chat:write'],
                'required_scopes_by_usage' => [
                    'knowledge_base' => ['channels:read', 'channels:history'],
                    'workflow_automation' => ['chat:write'],
                ],
                'scope_descriptions' => [
                    'channels:read' => 'List approved Slack channels.',
                    'channels:history' => 'Read messages from approved public channels.',
                    'groups:read' => 'List approved private channels.',
                    'groups:history' => 'Read messages from approved private channels.',
                    'chat:write' => 'Send approved workflow messages.',
                ],
            ],
            'github' => [
                'default_scopes' => ['read:org', 'repo:status'],
                'allowed_scopes' => ['read:org', 'repo:status', 'public_repo', 'repo'],
                'required_scopes_by_usage' => [
                    'knowledge_base' => ['public_repo'],
                    'developer_platform' => ['read:org'],
                    'workflow_automation' => ['repo'],
                ],
                'scope_descriptions' => [
                    'read:org' => 'Read organization membership for repository discovery.',
                    'repo:status' => 'Read repository status metadata.',
                    'public_repo' => 'Read selected public repositories.',
                    'repo' => 'Access selected private repositories and approved write workflows.',
                ],
            ],
            'notion' => [
                'default_scopes' => ['read_content'],
                'allowed_scopes' => ['read_content', 'read_user'],
                'required_scopes_by_usage' => [
                    'knowledge_base' => ['read_content'],
                ],
                'scope_descriptions' => [
                    'read_content' => 'Read selected Notion pages and databases.',
                    'read_user' => 'Read workspace user metadata for attribution.',
                ],
            ],
            default => null,
        };

        if ($oauth) {
            $schema['oauth'] = $oauth;
        }

        return $schema;
    }

    private function ensureCredential(Connection $connection, ?User $actor): void
    {
        if ($connection->credentials()->exists()) {
            return;
        }

        $secret = match ($connection->auth_type) {
            AuthenticationType::OAuth2 => ['access_token' => 'seed_access_token_'.$connection->id, 'refresh_token' => 'seed_refresh_token_'.$connection->id],
            AuthenticationType::DatabaseCredentials => ['username' => 'readonly_user', 'password' => 'seed_database_password_'.$connection->id],
            AuthenticationType::ApiKey => ['api_key' => 'sk_seed_connection_'.$connection->id.'_8P3X'],
            default => ['secret' => 'seed_secret_'.$connection->id],
        };

        ConnectionCredential::create([
            'tenant_id' => tenant('id'),
            'connection_id' => $connection->id,
            'type' => $connection->auth_type,
            'status' => $connection->credential_status === CredentialStatus::Expired ? CredentialStatus::Expired : CredentialStatus::Active,
            'encrypted_payload' => $secret,
            'masked_secret' => 'seed••••••••'.$connection->id,
            'metadata' => ['seeded' => true, 'scope_model' => 'least_privilege'],
            'last_used_at' => $connection->last_successful_check_at,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    private function ensureOperationalSamples(Connection $connection): void
    {
        if (! $connection->healthChecks()->exists()) {
            ConnectionHealthCheck::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'status' => $connection->status,
                'health_status' => $connection->health_status,
                'duration_ms' => rand(120, 900),
                'error_code' => $connection->last_error_code,
                'message' => $connection->last_error_message ?: 'Health check completed.',
                'result' => ['seeded' => true],
                'checked_at' => $connection->last_checked_at ?? now(),
            ]);
        }

        if ($connection->integration && ! $connection->rateLimits()->exists()) {
            ProviderRateLimit::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'provider' => $connection->integration->provider,
                'bucket' => 'default',
                'limit' => 10000,
                'remaining' => $connection->health_status === ConnectionHealth::RateLimited ? 0 : rand(1200, 9200),
                'resets_at' => now()->addHours(1),
                'backoff_until' => $connection->health_status === ConnectionHealth::RateLimited ? now()->addMinutes(20) : null,
                'headers' => ['x-ratelimit-limit' => 10000, 'x-ratelimit-remaining' => '[redacted]'],
                'observed_at' => now()->subMinutes(rand(3, 60)),
            ]);
        }

        $run = $connection->syncRuns()->latest()->first();

        if ($run && ! $run->items()->exists()) {
            foreach (range(1, 3) as $index) {
                SyncRunItem::create([
                    'tenant_id' => tenant('id'),
                    'sync_run_id' => $run->id,
                    'data_source_id' => $run->data_source_id,
                    'external_id' => 'seed-item-'.$connection->id.'-'.$index,
                    'operation' => $index === 1 ? 'create' : 'update',
                    'status' => 'processed',
                    'content_hash' => hash('sha256', $connection->id.'-'.$index),
                    'metadata' => ['seeded' => true],
                ]);
            }
        }
    }

    private function ensureActionSamples(Connection $connection): void
    {
        $integrationKey = $connection->integration?->key;
        $definitions = match ($integrationKey) {
            'hubspot' => [
                ['search_contacts', 'Search contacts', 'Find CRM contacts by email, name, or company.', 'low', false, true, true],
                ['create_note', 'Create note', 'Create a CRM note on an approved record.', 'medium', false, true, true],
                ['update_deal_stage', 'Update deal stage', 'Move a deal to an approved lifecycle stage.', 'high', true, false, true],
            ],
            'slack' => [
                ['search_messages', 'Search messages', 'Search approved Slack channel history.', 'low', false, true, true],
                ['send_message', 'Send message', 'Send a message to an approved channel.', 'medium', true, false, true],
            ],
            'github' => [
                ['search_issues', 'Search issues', 'Search approved repositories for issues and pull requests.', 'low', false, true, true],
                ['create_issue', 'Create issue', 'Open a GitHub issue in an approved repository.', 'medium', true, false, true],
            ],
            'custom-rest' => [
                ['call_shipping_status', 'Call shipping status', 'Fetch shipment status from the shipping API.', 'low', false, true, true],
                ['create_shipping_label', 'Create shipping label', 'Create an outbound shipping label.', 'high', true, false, true],
            ],
            default => [
                ['search_resources', 'Search resources', 'Search approved provider resources.', 'low', false, true, true],
            ],
        };

        foreach ($definitions as [$key, $name, $description, $risk, $approval, $ai, $workflow]) {
            ConnectionAction::updateOrCreate(
                ['connection_id' => $connection->id, 'key' => $key],
                [
                    'tenant_id' => tenant('id'),
                    'connection_integration_id' => $connection->connection_integration_id,
                    'name' => $name,
                    'description' => $description,
                    'risk_level' => $risk,
                    'requires_approval' => $approval,
                    'enabled_for_ai' => $ai,
                    'enabled_for_workflows' => $workflow,
                    'input_schema' => ['type' => 'object', 'additionalProperties' => false],
                    'output_schema' => ['type' => 'object'],
                    'capabilities' => ['actions.execute'],
                    'configuration' => ['idempotent' => true],
                    'status' => 'active',
                ]
            );
        }

        $triggers = match ($integrationKey) {
            'slack' => [['message_posted', 'Slack message posted', 'New message in an approved channel.']],
            'github' => [['issue_opened', 'GitHub issue opened', 'New issue opened in an approved repository.']],
            'hubspot' => [['contact_created', 'HubSpot contact created', 'New CRM contact created.']],
            'google-drive' => [['file_added', 'File added to Drive', 'File added under an approved folder.']],
            default => [['resource_changed', 'Resource changed', 'Approved remote resource changed.']],
        };

        foreach ($triggers as [$key, $name, $description]) {
            ConnectionTrigger::updateOrCreate(
                ['connection_id' => $connection->id, 'key' => $key],
                [
                    'tenant_id' => tenant('id'),
                    'connection_integration_id' => $connection->connection_integration_id,
                    'name' => $name,
                    'description' => $description,
                    'event_schema' => ['type' => 'object'],
                    'configuration' => ['dedupe' => true],
                    'status' => 'active',
                ]
            );
        }

        if ($connection->connection_type === ConnectionType::Api && ! $connection->apiOperations()->exists()) {
            ApiOperation::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'key' => 'get_order_status',
                'name' => 'Get order status',
                'method' => 'GET',
                'path' => '/orders/{order_id}',
                'headers' => ['Authorization' => '[redacted]'],
                'query_schema' => ['order_id' => ['type' => 'string']],
                'risk_level' => 'low',
                'enabled_for_ai' => true,
                'enabled_for_workflows' => true,
            ]);
        }

        $dataSource = $connection->dataSources()->where('resource_type', ResourceType::DatabaseTable->value)->first();

        if ($dataSource && ! $dataSource->databaseConfig()->exists()) {
            DatabaseDataSourceConfig::create([
                'tenant_id' => tenant('id'),
                'data_source_id' => $dataSource->id,
                'schema_name' => 'public',
                'table_name' => 'customer_accounts',
                'primary_key' => 'id',
                'incremental_column' => 'updated_at',
                'allowed_columns' => ['id', 'name', 'email', 'company', 'status', 'updated_at'],
                'excluded_columns' => ['password_hash', 'reset_token', 'internal_secret'],
                'filters' => [['column' => 'status', 'operator' => '=', 'value' => 'active']],
                'row_limit' => 10000,
                'read_only' => true,
                'validated_at' => now(),
            ]);
        }

        ConnectionAgentAccess::updateOrCreate(
            ['connection_id' => $connection->id, 'agent_key' => 'sales-agent'],
            [
                'tenant_id' => tenant('id'),
                'allowed_actions' => ['search_contacts', 'search_resources', 'search_messages', 'search_issues', 'call_shipping_status'],
                'allowed_resources' => ['*'],
                'read_only' => true,
                'approval_required' => true,
                'rate_limit_per_hour' => 120,
            ]
        );

        ConnectionWorkflowAccess::updateOrCreate(
            ['connection_id' => $connection->id, 'workflow_key' => 'customer-support-routing'],
            [
                'tenant_id' => tenant('id'),
                'allowed_actions' => ['*'],
                'allowed_triggers' => ['*'],
                'approval_required' => false,
            ]
        );

        foreach (['api_request' => rand(20, 120), 'workflow_usage' => rand(1, 12), 'ai_tool_usage' => rand(1, 18)] as $type => $quantity) {
            ConnectionUsageRecord::firstOrCreate(
                ['tenant_id' => tenant('id'), 'connection_id' => $connection->id, 'usage_type' => $type, 'usage_date' => today()],
                ['quantity' => $quantity, 'unit' => 'count', 'metadata' => ['seeded' => true], 'created_at' => now()]
            );
        }
    }

    private function ensureWebhookSample(?User $actor): void
    {
        $connection = Connection::query()
            ->whereHas('integration', fn ($query) => $query->where('key', 'slack'))
            ->first();

        if (! $connection || WebhookEndpoint::query()->where('connection_id', $connection->id)->exists()) {
            WebhookEvent::query()->where('connection_id', $connection?->id)->get()->each(fn (WebhookEvent $event) => $this->ensureWebhookAttempt($event));

            return;
        }

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => tenant('id'),
            'connection_id' => $connection->id,
            'name' => 'Slack event receiver',
            'provider' => 'Slack',
            'status' => 'active',
            'endpoint_path' => 'hooks/'.tenant('id').'/'.Str::random(24),
            'encrypted_secret' => 'seed_webhook_secret',
            'event_types' => ['message.channels', 'app_mention'],
            'configuration' => ['replay_window_seconds' => 300, 'dedupe' => true],
            'last_received_at' => now()->subMinutes(18),
        ]);

        $event = WebhookEvent::create([
            'tenant_id' => tenant('id'),
            'webhook_endpoint_id' => $endpoint->id,
            'connection_id' => $connection->id,
            'provider_event_id' => 'seed_evt_'.Str::random(8),
            'event_type' => 'message.channels',
            'status' => 'processed',
            'http_method' => 'POST',
            'headers' => ['x-signature' => '[redacted]'],
            'payload' => ['channel' => '#support', 'text' => 'New support message received.'],
            'payload_hash' => hash('sha256', '#support'),
            'payload_size' => 72,
            'signature' => '[redacted]',
            'received_at' => now()->subMinutes(18),
            'processed_at' => now()->subMinutes(17),
            'response_status' => 202,
            'latency_ms' => 42,
        ]);

        $this->ensureWebhookAttempt($event);

        ConnectionLog::create([
            'tenant_id' => tenant('id'),
            'connection_id' => $connection->id,
            'event' => 'webhook.received',
            'status' => 'ok',
            'level' => 'info',
            'message' => 'Slack webhook received and processed.',
            'actor_type' => $actor ? User::class : null,
            'actor_id' => $actor?->id,
            'context' => ['endpoint' => $endpoint->name],
            'created_at' => now()->subMinutes(17),
        ]);
    }

    private function ensureWebhookAttempt(WebhookEvent $event): void
    {
        if ($event->attempts()->exists()) {
            return;
        }

        WebhookDeliveryAttempt::create([
            'tenant_id' => tenant('id'),
            'webhook_event_id' => $event->id,
            'attempt' => 1,
            'status' => $event->status === 'failed' ? 'failed' : 'accepted',
            'response_status' => $event->response_status ?? 202,
            'latency_ms' => $event->latency_ms ?? 42,
            'attempted_at' => $event->received_at ?? now(),
        ]);
    }
}
