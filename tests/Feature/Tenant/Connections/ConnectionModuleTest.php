<?php

namespace Tests\Feature\Tenant\Connections;

use App\Enums\Connections\AuthenticationType;
use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Enums\Connections\ConnectionType;
use App\Enums\Connections\CredentialStatus;
use App\Enums\Connections\Environment;
use App\Enums\Connections\ResourceType;
use App\Enums\Connections\SyncStatus;
use App\Jobs\Connections\RunConnectionSyncJob;
use App\Jobs\Connections\TestConnectionJob;
use App\Models\Connections\ApiOperation;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionAgentAccess;
use App\Models\Connections\ConnectionAction;
use App\Models\Connections\ConnectionCredential;
use App\Models\Connections\ConnectionIdempotencyKey;
use App\Models\Connections\ConnectionIntegration;
use App\Models\Connections\ConnectionLog;
use App\Models\Connections\ConnectionResourcePermission;
use App\Models\Connections\CredentialRotation;
use App\Models\Connections\DataSource;
use App\Models\Connections\OAuthAuthorizationState;
use App\Models\Connections\SyncRun;
use App\Models\Connections\WebhookDeliveryAttempt;
use App\Models\Connections\WebhookEvent;
use App\Models\Connections\WebhookEndpoint;
use App\Services\Connections\ConnectionActionExecutionService;
use App\Services\Connections\ConnectionResourcePermissionService;
use App\Services\Connections\ConnectionUsageService;
use App\Services\Connections\CredentialVault;
use App\Services\Connections\ProviderRateLimitService;
use App\Services\Connections\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ConnectionModuleTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    public function test_seeded_tenant_administrator_can_open_connections_module_pages(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Connections Admin'], 'Tenant Administrator');

        foreach ([
            '/connections',
            '/connections/all',
            '/connections/apps',
            '/connections/data-sources',
            '/connections/api',
            '/connections/databases',
            '/connections/webhooks',
            '/connections/mcp',
            '/connections/sync-jobs',
            '/connections/logs',
            '/connections/failed',
            '/connections/credentials',
            '/connections/settings',
        ] as $path) {
            $response = $this->actingAs($admin, 'tenant')->get("http://{$domain}{$path}");

            $this->assertSame(200, $response->getStatusCode(), "Expected {$path} to load.");
        }
    }

    public function test_user_without_connections_permission_is_forbidden(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $user = $this->createTenantUser($tenant, ['name' => 'No Connections'], null);

        $this->actingAs($user, 'tenant')
            ->get("http://{$domain}/connections")
            ->assertForbidden();
    }

    public function test_tenant_connections_seed_realistic_catalog_and_sample_data(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        try {
            $this->assertGreaterThanOrEqual(8, ConnectionIntegration::query()->count());
            $this->assertDatabaseHas('connection_integrations', ['key' => 'google-drive']);
            $this->assertDatabaseHas('connection_integrations', ['key' => 'hubspot']);
            $this->assertGreaterThanOrEqual(6, Connection::query()->count());
            $this->assertGreaterThanOrEqual(6, DataSource::query()->count());
        } finally {
            tenancy()->end();
        }
    }

    public function test_credentials_are_masked_and_not_serialized(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Credential Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->firstOrFail();
            $credential = app(CredentialVault::class)->store($connection, AuthenticationType::ApiKey, [
                'api_key' => 'sk_live_super_secret_8P3X',
            ], $admin);

            $this->assertSame('sk_liv••••••••8P3X', $credential->masked_secret);
            $this->assertArrayNotHasKey('encrypted_payload', $credential->toArray());
            $this->assertSame('[redacted]', app(\App\Services\Connections\SecretRedactor::class)->redact(['api_key' => 'secret'])['api_key']);
        } finally {
            tenancy()->end();
        }
    }

    public function test_action_execution_service_rejects_actions_from_another_connection(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Action Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->firstOrFail();
            $otherAction = ConnectionAction::query()
                ->where('connection_id', '!=', $connection->id)
                ->firstOrFail();

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('This action does not belong to the selected connection.');

            app(ConnectionActionExecutionService::class)->execute($connection, $otherAction, [], $admin);
        } finally {
            tenancy()->end();
        }
    }

    public function test_ai_agent_action_execution_requires_explicit_grant(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        try {
            $slack = Connection::query()
                ->whereHas('integration', fn ($query) => $query->where('key', 'slack'))
                ->firstOrFail();

            $allowedAction = $slack->actions()->where('key', 'search_messages')->firstOrFail();
            $execution = app(ConnectionActionExecutionService::class)->execute(
                $slack,
                $allowedAction,
                ['query' => 'Jane'],
                agentKey: 'sales-agent',
                idempotencyKey: 'agent-search-messages',
            );

            $this->assertSame('waiting_for_approval', $execution->status);
            $this->assertTrue($execution->approval_required);

            $blockedAction = $slack->actions()->where('key', 'send_message')->firstOrFail();
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('This AI agent is not allowed to execute this connection action.');

            app(ConnectionActionExecutionService::class)->execute(
                $slack,
                $blockedAction,
                ['note' => 'Do not write'],
                agentKey: 'sales-agent',
                idempotencyKey: 'agent-send-message',
            );
        } finally {
            tenancy()->end();
        }
    }

    public function test_tenant_admin_can_rotate_connection_credential_without_exposing_secret(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Credential Rotator'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->firstOrFail();
            $oldCredential = app(CredentialVault::class)->store($connection, AuthenticationType::ApiKey, [
                'api_key' => 'sk_live_original_secret_1234',
            ], $admin);
            $oldCredentialId = $oldCredential->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/credentials/{$oldCredentialId}/rotate", [
                'credential' => ['api_key' => 'sk_live_replacement_secret_9876'],
                'reason' => 'Quarterly rotation',
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $oldCredential = ConnectionCredential::query()->findOrFail($oldCredentialId);
            $newCredential = ConnectionCredential::query()
                ->where('connection_id', $oldCredential->connection_id)
                ->where('id', '!=', $oldCredential->id)
                ->orderByDesc('id')
                ->firstOrFail();

            $this->assertSame(CredentialStatus::Rotated, $oldCredential->status);
            $this->assertSame(CredentialStatus::Active, $newCredential->status);
            $this->assertStringStartsWith('sk_live', $newCredential->masked_secret);
            $this->assertStringEndsWith('9876', $newCredential->masked_secret);
            $this->assertStringNotContainsString('replacement_secret', $newCredential->masked_secret);
            $this->assertArrayNotHasKey('encrypted_payload', $newCredential->toArray());
            $this->assertDatabaseHas('credential_rotations', ['status' => 'completed', 'connection_credential_id' => $newCredential->id]);

            $log = ConnectionLog::query()->where('event', 'credential.rotated')->latest()->firstOrFail();
            $this->assertSame('[redacted]', app(\App\Services\Connections\SecretRedactor::class)->redact(['api_key' => 'sk_live_replacement_secret_9876'])['api_key']);
            $this->assertStringNotContainsString('replacement_secret', json_encode($log->context));
        } finally {
            tenancy()->end();
        }
    }

    public function test_revoking_last_active_credential_requires_reauthentication(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Credential Revoker'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $credential = ConnectionCredential::query()->where('status', CredentialStatus::Active->value)->firstOrFail();
            $connectionId = $credential->connection_id;
            $credentialId = $credential->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/credentials/{$credentialId}/revoke", [
                'reason' => 'Provider access removed',
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $credential = ConnectionCredential::query()->findOrFail($credentialId);
            $connection = Connection::query()->findOrFail($connectionId);

            $this->assertSame(CredentialStatus::Revoked, $credential->status);
            $this->assertSame(CredentialStatus::Revoked, $connection->credential_status);
            $this->assertSame(ConnectionStatus::AuthenticationRequired, $connection->status);
            $this->assertSame(ConnectionHealth::AuthenticationExpired, $connection->health_status);
            $this->assertTrue(CredentialRotation::query()->where('status', 'revoked')->where('connection_credential_id', $credentialId)->exists());
            $this->assertTrue(ConnectionLog::query()->where('event', 'credential.revoked')->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_safe_connection_delete_requires_exact_confirmation(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Delete Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->firstOrFail();
            $connectionId = $connection->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->delete("http://{$domain}/connections/{$connectionId}", [
                'confirmation' => 'wrong name',
                'reason' => 'Testing guard',
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $this->assertNull(Connection::query()->findOrFail($connectionId)->deleted_at);
        } finally {
            tenancy()->end();
        }
    }

    public function test_tenant_admin_can_delete_connection_safely_with_dependency_cleanup(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Safe Delete Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()
                ->whereHas('integration', fn ($query) => $query->where('key', 'slack'))
                ->firstOrFail();

            $syncRun = $connection->syncRuns()->create([
                'tenant_id' => tenant('id'),
                'data_source_id' => $connection->dataSources()->value('id'),
                'sync_type' => 'manual',
                'status' => SyncStatus::Queued,
                'triggered_by' => $admin->id,
                'trigger_source' => 'test',
            ]);

            $connectionId = $connection->id;
            $connectionName = $connection->name;
            $credentialIds = $connection->credentials()->pluck('id')->all();
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->delete("http://{$domain}/connections/{$connectionId}", [
                'confirmation' => $connectionName,
                'reason' => 'Retiring provider',
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::withTrashed()->findOrFail($connectionId);

            $this->assertNotNull($connection->deleted_at);
            $this->assertSame(ConnectionStatus::Archived, $connection->status);
            $this->assertSame(ConnectionHealth::Disabled, $connection->health_status);
            $this->assertSame(CredentialStatus::Revoked, $connection->credential_status);
            $this->assertSame(SyncStatus::Cancelled, SyncRun::query()->findOrFail($syncRun->id)->status);

            $this->assertSame(0, $connection->agentAccess()->count());
            $this->assertSame(0, $connection->workflowAccess()->count());
            $this->assertSame(0, $connection->accessGrants()->count());

            $this->assertTrue(DataSource::withTrashed()->where('connection_id', $connectionId)->whereNotNull('deleted_at')->exists());
            $this->assertTrue(WebhookEndpoint::withTrashed()->where('connection_id', $connectionId)->where('status', 'disabled')->whereNotNull('deleted_at')->exists());

            foreach ($credentialIds as $credentialId) {
                $credential = ConnectionCredential::query()->findOrFail($credentialId);
                $this->assertSame(CredentialStatus::Revoked, $credential->status);
                $this->assertNull($credential->encrypted_payload);
            }

            $log = ConnectionLog::query()->where('connection_id', $connectionId)->where('event', 'connection.deleted')->firstOrFail();
            $this->assertTrue($log->context['credentials_scrubbed']);
            $this->assertSame('Retiring provider', $log->context['reason']);
        } finally {
            tenancy()->end();
        }
    }

    public function test_failed_webhook_event_can_be_replayed_with_audit_trail(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Webhook Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $endpoint = WebhookEndpoint::query()->where('status', 'active')->firstOrFail();
            $event = WebhookEvent::create([
                'tenant_id' => tenant('id'),
                'webhook_endpoint_id' => $endpoint->id,
                'connection_id' => $endpoint->connection_id,
                'provider_event_id' => 'failed-replay-test',
                'event_type' => 'shipment.failed',
                'status' => 'failed',
                'http_method' => 'POST',
                'headers' => ['Authorization' => '[redacted]'],
                'payload' => ['event_id' => 'failed-replay-test', 'token' => '[redacted]'],
                'payload_hash' => hash('sha256', 'failed-replay-test'),
                'payload_size' => 42,
                'received_at' => now(),
                'response_status' => 400,
                'latency_ms' => 10,
                'error_message' => 'Workflow target unavailable.',
            ]);
            WebhookDeliveryAttempt::create([
                'tenant_id' => tenant('id'),
                'webhook_event_id' => $event->id,
                'attempt' => 1,
                'status' => 'failed',
                'response_status' => 400,
                'error_message' => 'Workflow target unavailable.',
                'attempted_at' => now(),
            ]);
            $eventId = $event->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/webhooks/events/{$eventId}/replay")
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $event = WebhookEvent::query()->findOrFail($eventId);

            $this->assertSame('replay_queued', $event->status);
            $this->assertNotNull($event->replayed_at);
            $this->assertSame($admin->id, $event->replayed_by);
            $this->assertSame(2, $event->attempts()->count());
            $this->assertTrue($event->attempts()->where('status', 'replay_queued')->exists());
            $this->assertTrue(ConnectionLog::query()->where('event', 'webhook.replayed')->where('connection_id', $event->connection_id)->exists());
            $this->assertTrue(ConnectionIdempotencyKey::query()->where('operation', 'webhook.replay')->where('status', 'completed')->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_non_failed_webhook_event_cannot_be_replayed(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Webhook Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $endpoint = WebhookEndpoint::query()->where('status', 'active')->firstOrFail();
            $event = WebhookEvent::create([
                'tenant_id' => tenant('id'),
                'webhook_endpoint_id' => $endpoint->id,
                'connection_id' => $endpoint->connection_id,
                'provider_event_id' => 'received-replay-test',
                'event_type' => 'shipment.received',
                'status' => 'received',
                'http_method' => 'POST',
                'headers' => [],
                'payload' => ['event_id' => 'received-replay-test'],
                'payload_hash' => hash('sha256', 'received-replay-test'),
                'payload_size' => 32,
                'received_at' => now(),
                'response_status' => 202,
                'latency_ms' => 8,
            ]);
            WebhookDeliveryAttempt::create([
                'tenant_id' => tenant('id'),
                'webhook_event_id' => $event->id,
                'attempt' => 1,
                'status' => 'accepted',
                'response_status' => 202,
                'attempted_at' => now(),
            ]);
            $eventId = $event->id;
            $attemptsBefore = $event->attempts()->count();
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/webhooks/events/{$eventId}/replay")
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $event = WebhookEvent::query()->findOrFail($eventId);

            $this->assertSame('received', $event->status);
            $this->assertNull($event->replayed_at);
            $this->assertSame($attemptsBefore, $event->attempts()->count());
        } finally {
            tenancy()->end();
        }
    }

    public function test_signed_inbound_webhook_is_accepted_and_duplicates_are_idempotent(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $secret = 'whsec_'.Str::random(32);
        $payload = json_encode(['event_id' => 'evt_signed_accept', 'type' => 'order.created', 'api_key' => 'secret-value']);
        $timestamp = (string) now()->timestamp;

        tenancy()->initialize($tenant);

        try {
            $endpoint = $this->createSignedWebhookEndpoint($secret, ['order.created']);
            $endpointPath = $endpoint->endpoint_path;
            $endpointId = $endpoint->id;
        } finally {
            tenancy()->end();
        }

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PROMPTBOT_TIMESTAMP' => $timestamp,
            'HTTP_X_PROMPTBOT_SIGNATURE' => $this->webhookSignature($payload, $secret, $timestamp),
            'HTTP_X_EVENT_ID' => 'evt_signed_accept',
            'HTTP_X_EVENT_TYPE' => 'order.created',
        ];

        $this->call('POST', "http://{$domain}/webhooks/inbound/{$endpointPath}", [], [], [], $headers, $payload)
            ->assertStatus(202)
            ->assertJsonPath('status', 'accepted');

        $this->call('POST', "http://{$domain}/webhooks/inbound/{$endpointPath}", [], [], [], $headers, $payload)
            ->assertStatus(202)
            ->assertJsonPath('status', 'duplicate');

        tenancy()->initialize($tenant);

        try {
            $event = WebhookEvent::query()
                ->where('webhook_endpoint_id', $endpointId)
                ->where('provider_event_id', 'evt_signed_accept')
                ->firstOrFail();

            $this->assertSame('received', $event->status);
            $this->assertSame('order.created', $event->event_type);
            $this->assertSame('[redacted]', $event->payload['api_key']);
            $this->assertSame(2, $event->attempts()->count());
        } finally {
            tenancy()->end();
        }
    }

    public function test_inbound_webhook_rejects_stale_timestamp_and_invalid_signature(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $secret = 'whsec_'.Str::random(32);
        $payload = json_encode(['event_id' => 'evt_stale', 'type' => 'order.created']);
        $staleTimestamp = (string) now()->subMinutes(10)->timestamp;

        tenancy()->initialize($tenant);

        try {
            $endpoint = $this->createSignedWebhookEndpoint($secret, ['order.created']);
            $endpointPath = $endpoint->endpoint_path;
            $endpointId = $endpoint->id;
        } finally {
            tenancy()->end();
        }

        $this->call('POST', "http://{$domain}/webhooks/inbound/{$endpointPath}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PROMPTBOT_TIMESTAMP' => $staleTimestamp,
            'HTTP_X_PROMPTBOT_SIGNATURE' => $this->webhookSignature($payload, $secret, $staleTimestamp),
            'HTTP_X_EVENT_ID' => 'evt_stale',
            'HTTP_X_EVENT_TYPE' => 'order.created',
        ], $payload)->assertStatus(400);

        $freshTimestamp = (string) now()->timestamp;

        $this->call('POST', "http://{$domain}/webhooks/inbound/{$endpointPath}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PROMPTBOT_TIMESTAMP' => $freshTimestamp,
            'HTTP_X_PROMPTBOT_SIGNATURE' => 'sha256=invalid',
            'HTTP_X_EVENT_ID' => 'evt_bad_signature',
            'HTTP_X_EVENT_TYPE' => 'order.created',
        ], $payload)->assertStatus(400);

        tenancy()->initialize($tenant);

        try {
            $this->assertSame(2, WebhookEvent::query()
                ->where('webhook_endpoint_id', $endpointId)
                ->where('status', 'failed')
                ->count());
            $this->assertTrue(WebhookEvent::query()
                ->where('webhook_endpoint_id', $endpointId)
                ->where('error_message', 'like', '%timestamp%')
                ->exists());
            $this->assertTrue(WebhookEvent::query()
                ->where('webhook_endpoint_id', $endpointId)
                ->where('error_message', 'like', '%signature%')
                ->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_inbound_webhook_rejects_disallowed_event_type(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $secret = 'whsec_'.Str::random(32);
        $payload = json_encode(['event_id' => 'evt_disallowed', 'type' => 'order.deleted']);
        $timestamp = (string) now()->timestamp;

        tenancy()->initialize($tenant);

        try {
            $endpoint = $this->createSignedWebhookEndpoint($secret, ['order.created']);
            $endpointPath = $endpoint->endpoint_path;
            $endpointId = $endpoint->id;
        } finally {
            tenancy()->end();
        }

        $this->call('POST', "http://{$domain}/webhooks/inbound/{$endpointPath}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PROMPTBOT_TIMESTAMP' => $timestamp,
            'HTTP_X_PROMPTBOT_SIGNATURE' => $this->webhookSignature($payload, $secret, $timestamp),
            'HTTP_X_EVENT_ID' => 'evt_disallowed',
            'HTTP_X_EVENT_TYPE' => 'order.deleted',
        ], $payload)->assertStatus(400);

        tenancy()->initialize($tenant);

        try {
            $event = WebhookEvent::query()
                ->where('webhook_endpoint_id', $endpointId)
                ->where('provider_event_id', 'evt_disallowed')
                ->firstOrFail();

            $this->assertSame('failed', $event->status);
            $this->assertStringContainsString('event type', $event->error_message);
        } finally {
            tenancy()->end();
        }
    }

    public function test_failed_sync_run_can_be_retried_when_failure_is_transient(): void
    {
        Queue::fake();

        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Sync Retry Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('status', ConnectionStatus::Active->value)->firstOrFail();
            $dataSource = $connection->dataSources()->first();
            $syncRun = $connection->syncRuns()->create([
                'tenant_id' => tenant('id'),
                'data_source_id' => $dataSource?->id,
                'sync_type' => 'manual',
                'status' => SyncStatus::Failed,
                'started_at' => now()->subMinutes(5),
                'completed_at' => now()->subMinutes(4),
                'error_code' => 'HTTP_503',
                'error_summary' => 'Provider unavailable.',
                'triggered_by' => $admin->id,
                'trigger_source' => 'manual',
            ]);
            $syncRunId = $syncRun->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/sync-jobs/{$syncRunId}/retry")
            ->assertRedirect();

        Queue::assertPushed(RunConnectionSyncJob::class);

        tenancy()->initialize($tenant);

        try {
            $syncRun = SyncRun::query()->findOrFail($syncRunId);

            $this->assertSame(SyncStatus::Retrying, $syncRun->status);
            $this->assertSame(1, $syncRun->retry_count);
            $this->assertTrue(ConnectionLog::query()->where('event', 'sync.retry_queued')->where('sync_run_id', $syncRun->id)->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_authentication_sync_failure_requires_manual_repair_before_retry(): void
    {
        Queue::fake();

        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Sync Retry Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->firstOrFail();
            $syncRun = $connection->syncRuns()->create([
                'tenant_id' => tenant('id'),
                'sync_type' => 'manual',
                'status' => SyncStatus::Failed,
                'started_at' => now()->subMinutes(5),
                'completed_at' => now()->subMinutes(4),
                'error_code' => 'AUTHENTICATION_REQUIRED',
                'error_summary' => 'Reconnect before syncing.',
                'triggered_by' => $admin->id,
                'trigger_source' => 'manual',
            ]);
            $syncRunId = $syncRun->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/sync-jobs/{$syncRunId}/retry")
            ->assertRedirect();

        Queue::assertNothingPushed();

        tenancy()->initialize($tenant);

        try {
            $this->assertSame(SyncStatus::Failed, SyncRun::query()->findOrFail($syncRunId)->status);
        } finally {
            tenancy()->end();
        }
    }

    public function test_failed_connections_page_exposes_recovery_metadata(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Recovery Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $integration = ConnectionIntegration::query()->firstOrFail();
            $connection = Connection::create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $integration->id,
                'name' => 'Failed Recovery Source',
                'status' => ConnectionStatus::AuthenticationRequired,
                'health_status' => ConnectionHealth::AuthenticationExpired,
                'connection_type' => ConnectionType::Application,
                'auth_type' => AuthenticationType::ApiKey,
                'environment' => Environment::Production,
                'credential_status' => CredentialStatus::Expired,
                'last_error_at' => now(),
                'last_error_code' => 'AUTHENTICATION_REQUIRED',
                'last_error_message' => 'Reconnect credentials before syncing.',
            ]);
            $connection->syncRuns()->create([
                'tenant_id' => tenant('id'),
                'sync_type' => 'manual',
                'status' => SyncStatus::Completed,
                'started_at' => now()->subDays(2),
                'completed_at' => now()->subDays(2),
                'items_discovered' => 10,
                'triggered_by' => $admin->id,
                'trigger_source' => 'manual',
            ]);
            $failedRun = $connection->syncRuns()->create([
                'tenant_id' => tenant('id'),
                'sync_type' => 'manual',
                'status' => SyncStatus::Failed,
                'started_at' => now()->subMinutes(5),
                'completed_at' => now()->subMinutes(4),
                'retry_count' => 2,
                'error_code' => 'HTTP_503',
                'error_summary' => 'Provider unavailable.',
                'triggered_by' => $admin->id,
                'trigger_source' => 'manual',
            ]);
            $connectionId = $connection->id;
            $failedRunId = $failedRun->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->get("http://{$domain}/connections/failed")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Admin/Connections/Failed/Index')
                ->where('connections.data.0.id', $connectionId)
                ->where('connections.data.0.failed_sync_runs_count', 1)
                ->where('connections.data.0.latest_failed_sync_run.id', $failedRunId)
                ->where('connections.data.0.latest_failed_sync_run.retry_count', 2));
    }

    public function test_failed_connection_reconnect_queues_validation_when_credentials_are_active(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Reconnect Admin'], 'Tenant Administrator');

        Queue::fake();

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('status', ConnectionStatus::Active->value)->firstOrFail();
            $connection->forceFill([
                'status' => ConnectionStatus::Error,
                'health_status' => ConnectionHealth::Error,
                'auth_type' => AuthenticationType::ApiKey,
                'credential_status' => CredentialStatus::Active,
                'last_error_at' => now(),
                'last_error_message' => 'Provider test failed.',
            ])->save();
            app(CredentialVault::class)->store($connection, AuthenticationType::ApiKey, ['api_key' => 'sk_reconnect_active'], $admin);
            $connectionId = $connection->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/{$connectionId}/reconnect")
            ->assertRedirect();

        Queue::assertPushed(TestConnectionJob::class);

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->findOrFail($connectionId);

            $this->assertSame(ConnectionStatus::Connecting, $connection->status);
            $this->assertSame(ConnectionHealth::NeedsAttention, $connection->health_status);
            $this->assertTrue(ConnectionLog::query()->where('event', 'connection.reconnect_requested')->where('connection_id', $connectionId)->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_failed_connection_retry_queues_latest_transient_sync_failure(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Retry Failed Admin'], 'Tenant Administrator');

        Queue::fake();

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('status', ConnectionStatus::Active->value)->firstOrFail();
            $syncRun = $connection->syncRuns()->create([
                'tenant_id' => tenant('id'),
                'sync_type' => 'manual',
                'status' => SyncStatus::Failed,
                'started_at' => now()->subMinutes(5),
                'completed_at' => now()->subMinutes(4),
                'error_code' => 'HTTP_503',
                'error_summary' => 'Provider unavailable.',
                'triggered_by' => $admin->id,
                'trigger_source' => 'manual',
            ]);
            $connectionId = $connection->id;
            $syncRunId = $syncRun->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/{$connectionId}/retry-failed-sync")
            ->assertRedirect();

        Queue::assertPushed(RunConnectionSyncJob::class);

        tenancy()->initialize($tenant);

        try {
            $this->assertSame(SyncStatus::Retrying, SyncRun::query()->findOrFail($syncRunId)->status);
        } finally {
            tenancy()->end();
        }
    }

    public function test_tenant_admin_can_edit_connection_metadata(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Connection Editor'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->firstOrFail();
            $connectionId = $connection->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->get("http://{$domain}/connections/{$connectionId}/edit")
            ->assertOk();

        $this->actingAs($admin, 'tenant')
            ->put("http://{$domain}/connections/{$connectionId}", [
                'name' => 'Edited support source',
                'description' => 'Updated from the recovery flow.',
                'connection_type' => ConnectionType::Application->value,
                'environment' => Environment::Production->value,
                'provider_account_name' => 'support@example.com',
                'usage' => ['knowledge_base'],
                'configuration' => ['read_only' => true],
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->findOrFail($connectionId);

            $this->assertSame('Edited support source', $connection->name);
            $this->assertSame('support@example.com', $connection->provider_account_name);
            $this->assertTrue(ConnectionLog::query()->where('event', 'connection.updated')->where('connection_id', $connectionId)->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_provider_rate_limit_headers_update_backoff_state(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('status', ConnectionStatus::Active->value)->firstOrFail();
            $retryAfter = now()->addMinutes(3);

            $rateLimit = app(ProviderRateLimitService::class)->record($connection, [
                'X-RateLimit-Limit' => '100',
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => now()->addMinutes(5)->timestamp,
                'Retry-After' => $retryAfter->toRfc7231String(),
            ]);

            $connection->refresh();

            $this->assertSame(100, $rateLimit->limit);
            $this->assertSame(0, $rateLimit->remaining);
            $this->assertNotNull($rateLimit->backoff_until);
            $this->assertTrue($rateLimit->backoff_until->between($retryAfter->copy()->subSecond(), $retryAfter->copy()->addSecond()));
            $this->assertSame(ConnectionStatus::RateLimited, $connection->status);
            $this->assertSame(ConnectionHealth::RateLimited, $connection->health_status);
        } finally {
            tenancy()->end();
        }
    }

    public function test_sync_during_rate_limit_reuses_paused_sync_run_without_hammering_provider(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Rate Limit Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('status', ConnectionStatus::Active->value)->firstOrFail();
            $dataSource = $connection->dataSources()->first();

            app(ProviderRateLimitService::class)->record($connection, [
                'Retry-After' => '180',
                'X-RateLimit-Remaining' => '0',
            ]);

            $service = app(SyncService::class);
            $firstRun = $service->run($connection->fresh(), $dataSource, $admin);
            $secondRun = $service->run($connection->fresh(), $dataSource, $admin);

            $this->assertSame($firstRun->id, $secondRun->id);
            $this->assertSame(SyncStatus::RateLimited, $firstRun->status);
            $this->assertSame(1, SyncRun::query()
                ->where('connection_id', $connection->id)
                ->where('data_source_id', $dataSource?->id)
                ->where('status', SyncStatus::RateLimited->value)
                ->where('error_code', 'RATE_LIMITED')
                ->count());
            $this->assertTrue(ConnectionLog::query()->where('event', 'sync.rate_limited')->where('connection_id', $connection->id)->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_tenant_admin_can_manage_connection_agent_workflow_and_access_grants(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Permissions Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('status', ConnectionStatus::Active->value)->firstOrFail();
            $action = $connection->actions()->create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $connection->connection_integration_id,
                'key' => 'safe_lookup_permission_test',
                'name' => 'Safe lookup permission test',
                'risk_level' => 'low',
                'enabled_for_ai' => true,
                'enabled_for_workflows' => true,
                'status' => 'active',
            ]);
            $trigger = $connection->triggers()->create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $connection->connection_integration_id,
                'key' => 'record_changed_permission_test',
                'name' => 'Record changed permission test',
                'status' => 'active',
            ]);
            $connectionId = $connection->id;
            $actionKey = $action->key;
            $triggerKey = $trigger->key;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->get("http://{$domain}/connections/permissions")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Admin/Connections/Permissions/Index'));

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/{$connectionId}/permissions/agents", [
                'agent_key' => 'sales-agent',
                'allowed_actions' => [$actionKey],
                'allowed_resources' => ['crm.contacts'],
                'read_only' => true,
                'approval_required' => true,
                'rate_limit_per_hour' => 25,
            ])
            ->assertRedirect();

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/{$connectionId}/permissions/workflows", [
                'workflow_key' => 'lead-enrichment',
                'allowed_actions' => [$actionKey],
                'allowed_triggers' => [$triggerKey],
                'approval_required' => true,
            ])
            ->assertRedirect();

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/{$connectionId}/permissions/access-grants", [
                'subject_type' => 'workspace',
                'capabilities' => ['resources.view', 'actions.execute'],
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $this->assertDatabaseHas('connection_agent_access', [
                'connection_id' => $connectionId,
                'agent_key' => 'sales-agent',
                'read_only' => 1,
                'approval_required' => 1,
                'rate_limit_per_hour' => 25,
            ]);
            $this->assertDatabaseHas('connection_workflow_access', [
                'connection_id' => $connectionId,
                'workflow_key' => 'lead-enrichment',
                'approval_required' => 1,
            ]);
            $this->assertDatabaseHas('connection_access_grants', [
                'connection_id' => $connectionId,
                'subject_type' => 'workspace',
                'subject_id' => null,
            ]);
            $this->assertTrue(ConnectionLog::query()->where('event', 'permissions.agent_grant_saved')->where('connection_id', $connectionId)->exists());
            $this->assertTrue(ConnectionLog::query()->where('event', 'permissions.workflow_grant_saved')->where('connection_id', $connectionId)->exists());
            $this->assertTrue(ConnectionLog::query()->where('event', 'permissions.access_grant_saved')->where('connection_id', $connectionId)->exists());

            $execution = app(ConnectionActionExecutionService::class)->execute(
                Connection::query()->findOrFail($connectionId),
                ConnectionAction::query()->where('connection_id', $connectionId)->where('key', $actionKey)->firstOrFail(),
                [],
                agentKey: 'sales-agent',
                idempotencyKey: 'permission-managed-agent-action',
            );

            $this->assertSame('waiting_for_approval', $execution->status);
            $this->assertTrue($execution->approval_required);
        } finally {
            tenancy()->end();
        }
    }

    public function test_connection_permission_grants_reject_wildcards_and_disabled_actions(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Permissions Guard'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('status', ConnectionStatus::Active->value)->firstOrFail();
            $disabledAction = $connection->actions()->create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $connection->connection_integration_id,
                'key' => 'disabled_permission_test',
                'name' => 'Disabled permission test',
                'risk_level' => 'low',
                'enabled_for_ai' => false,
                'enabled_for_workflows' => false,
                'status' => 'active',
            ]);
            $connectionId = $connection->id;
            $disabledActionKey = $disabledAction->key;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/permissions")
            ->post("http://{$domain}/connections/{$connectionId}/permissions/agents", [
                'agent_key' => 'unsafe-agent',
                'allowed_actions' => ['*'],
                'read_only' => true,
                'approval_required' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('allowed_actions');

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/permissions")
            ->post("http://{$domain}/connections/{$connectionId}/permissions/agents", [
                'agent_key' => 'unsafe-agent',
                'allowed_actions' => [$disabledActionKey],
                'read_only' => true,
                'approval_required' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('allowed_actions');

        tenancy()->initialize($tenant);

        try {
            $this->assertFalse(ConnectionAgentAccess::query()
                ->where('connection_id', $connectionId)
                ->where('agent_key', 'unsafe-agent')
                ->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_data_source_sync_denies_unselected_connection_resource(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Resource Sync Guard'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('status', ConnectionStatus::Active->value)->whereHas('dataSources')->firstOrFail();
            $dataSource = $connection->dataSources()->whereNotNull('connection_resource_id')->firstOrFail();
            $dataSource->resource()->firstOrFail()->forceFill(['selected_at' => null])->save();
            $previousSuccessfulSync = $dataSource->last_successful_sync_at?->toDateTimeString();

            $run = app(SyncService::class)->run($connection->fresh(), $dataSource->fresh(), $admin);

            $this->assertSame(SyncStatus::Failed, $run->status);
            $this->assertSame('RESOURCE_PERMISSION_DENIED', $run->error_code);
            $this->assertSame($previousSuccessfulSync, $dataSource->fresh()->last_successful_sync_at?->toDateTimeString());
            $this->assertTrue(ConnectionLog::query()
                ->where('connection_id', $connection->id)
                ->where('data_source_id', $dataSource->id)
                ->where('event', 'sync.resource_permission_denied')
                ->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_resource_permission_grants_are_enforced_during_sync(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        [$admin, $otherUser] = $this->createTenantUsers($tenant, [
            ['attributes' => ['name' => 'Resource Admin'], 'role' => 'Tenant Administrator'],
            ['attributes' => ['name' => 'Other Resource User'], 'role' => 'Tenant Administrator'],
        ]);

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('status', ConnectionStatus::Active->value)->whereHas('dataSources')->firstOrFail();
            $dataSource = $connection->dataSources()->whereNotNull('connection_resource_id')->firstOrFail();
            $resource = $dataSource->resource()->firstOrFail();
            $connectionId = $connection->id;
            $dataSourceId = $dataSource->id;
            $resourceId = $resource->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/resources/{$resourceId}/permissions", [
                'subject_type' => 'user',
                'subject_id' => $otherUser->id,
                'capabilities' => ['resources.sync'],
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $this->assertTrue(ConnectionResourcePermission::query()
                ->where('connection_resource_id', $resourceId)
                ->where('subject_type', 'user')
                ->where('subject_id', $otherUser->id)
                ->exists());

            $deniedRun = app(SyncService::class)->run(
                Connection::query()->findOrFail($connectionId),
                DataSource::query()->findOrFail($dataSourceId),
                $admin,
            );

            $this->assertSame(SyncStatus::Failed, $deniedRun->status);
            $this->assertSame('RESOURCE_PERMISSION_DENIED', $deniedRun->error_code);
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/resources/{$resourceId}/permissions", [
                'subject_type' => 'user',
                'subject_id' => $admin->id,
                'capabilities' => ['resources.view', 'resources.sync'],
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $allowedRun = app(SyncService::class)->run(
                Connection::query()->findOrFail($connectionId),
                DataSource::query()->findOrFail($dataSourceId),
                $admin,
            );

            $this->assertSame(SyncStatus::Completed, $allowedRun->status);
            $this->assertTrue(ConnectionLog::query()->where('event', 'permissions.resource_grant_saved')->where('connection_id', $connectionId)->exists());
            $this->assertTrue(ConnectionLog::query()->where('event', 'sync.completed')->where('connection_id', $connectionId)->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_agent_resource_keys_are_enforced_against_selected_resources(): void
    {
        [$tenant] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('status', ConnectionStatus::Active->value)->whereHas('dataSources')->firstOrFail();
            $resource = $connection->resources()->whereNotNull('selected_at')->firstOrFail();

            ConnectionAgentAccess::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'agent_key' => 'support-agent',
                'allowed_actions' => ['lookup_customer'],
                'allowed_resources' => [$resource->external_id],
                'read_only' => true,
                'approval_required' => true,
            ]);

            $service = app(ConnectionResourcePermissionService::class);
            $service->assertAgentResourceAllowed($connection, $resource->external_id, 'support-agent');

            $this->expectException(InvalidArgumentException::class);
            $service->assertAgentResourceAllowed($connection, '/Finance', 'support-agent');
        } finally {
            tenancy()->end();
        }
    }

    public function test_tenant_admin_can_create_safe_custom_api_operation(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'API Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $integration = ConnectionIntegration::query()->where('key', 'custom-rest')->firstOrFail();
            $connection = Connection::create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $integration->id,
                'name' => 'Safe API',
                'status' => ConnectionStatus::Active,
                'health_status' => ConnectionHealth::Healthy,
                'connection_type' => ConnectionType::Api,
                'auth_type' => AuthenticationType::None,
                'environment' => Environment::Production,
                'configuration' => ['base_url' => 'https://93.184.216.34'],
                'credential_status' => CredentialStatus::Missing,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $connectionId = $connection->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/{$connectionId}/api-operations", [
                'key' => 'get_customer',
                'name' => 'Get customer',
                'method' => 'GET',
                'path' => '/customers/{customer_id}',
                'headers' => ['Accept' => 'application/json'],
                'risk_level' => 'low',
                'enabled_for_ai' => true,
                'enabled_for_workflows' => true,
                'timeout_seconds' => 30,
                'max_response_kb' => 512,
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $this->assertTrue(ApiOperation::query()
                ->where('connection_id', $connectionId)
                ->where('key', 'get_customer')
                ->where('path', '/customers/{customer_id}')
                ->where('enabled_for_ai', true)
                ->exists());
            $this->assertTrue(ConnectionLog::query()->where('event', 'api_operation.saved')->where('connection_id', $connectionId)->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_custom_api_operation_rejects_internal_base_url_and_absolute_paths(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'API Guard'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $integration = ConnectionIntegration::query()->where('key', 'custom-rest')->firstOrFail();
            $connection = Connection::create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $integration->id,
                'name' => 'Unsafe API',
                'status' => ConnectionStatus::Active,
                'health_status' => ConnectionHealth::Healthy,
                'connection_type' => ConnectionType::Api,
                'auth_type' => AuthenticationType::None,
                'environment' => Environment::Production,
                'configuration' => ['base_url' => 'https://127.0.0.1'],
                'credential_status' => CredentialStatus::Missing,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $connectionId = $connection->id;
        } finally {
            tenancy()->end();
        }

        $payload = [
            'key' => 'unsafe_customer',
            'name' => 'Unsafe customer',
            'method' => 'GET',
            'path' => '/customers/{customer_id}',
            'headers' => [],
            'risk_level' => 'low',
            'enabled_for_ai' => true,
            'enabled_for_workflows' => false,
            'timeout_seconds' => 30,
            'max_response_kb' => 512,
        ];

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/api")
            ->post("http://{$domain}/connections/{$connectionId}/api-operations", $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('path');

        tenancy()->initialize($tenant);

        try {
            Connection::query()->findOrFail($connectionId)->forceFill([
                'configuration' => ['base_url' => 'https://93.184.216.34'],
            ])->save();
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/api")
            ->post("http://{$domain}/connections/{$connectionId}/api-operations", [
                ...$payload,
                'path' => 'https://example.com/customers/{customer_id}',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('path');

        tenancy()->initialize($tenant);

        try {
            $this->assertFalse(ApiOperation::query()
                ->where('connection_id', $connectionId)
                ->where('key', 'unsafe_customer')
                ->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_tenant_admin_can_save_safe_database_data_source_configuration(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Database Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('connection_type', ConnectionType::Database->value)->firstOrFail();
            $connection->forceFill(['configuration' => ['host' => '93.184.216.34', 'read_only' => true]])->save();
            $dataSource = $connection->dataSources()->where('resource_type', ResourceType::DatabaseTable->value)->firstOrFail();
            $dataSourceId = $dataSource->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/data-sources/{$dataSourceId}/database-config", [
                'schema_name' => 'public',
                'table_name' => 'customers',
                'primary_key' => 'id',
                'incremental_column' => 'updated_at',
                'allowed_columns' => ['id', 'email', 'password_hash', 'updated_at'],
                'excluded_columns' => ['password_hash'],
                'filters' => [['column' => 'status', 'operator' => '=', 'value' => 'active']],
                'row_limit' => 5000,
                'read_only' => true,
                'raw_sql' => 'select id, email, updated_at from customers',
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $dataSource = DataSource::query()->findOrFail($dataSourceId);
            $config = $dataSource->databaseConfig()->firstOrFail();

            $this->assertSame('customers', $config->table_name);
            $this->assertSame(['id', 'email', 'password_hash', 'updated_at'], $config->allowed_columns);
            $this->assertSame(['password_hash'], $config->excluded_columns);
            $this->assertSame(5000, $config->row_limit);
            $this->assertTrue($config->read_only);
            $this->assertNotNull($config->validated_at);
            $this->assertTrue(ConnectionLog::query()->where('event', 'database.query_configuration_changed')->where('data_source_id', $dataSourceId)->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_database_data_source_configuration_rejects_sensitive_columns_and_unsafe_sql(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Database Guard'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connection = Connection::query()->where('connection_type', ConnectionType::Database->value)->firstOrFail();
            $connection->forceFill(['configuration' => ['host' => '93.184.216.34', 'read_only' => true]])->save();
            $dataSource = $connection->dataSources()->where('resource_type', ResourceType::DatabaseTable->value)->firstOrFail();
            $dataSourceId = $dataSource->id;
        } finally {
            tenancy()->end();
        }

        $payload = [
            'schema_name' => 'public',
            'table_name' => 'customers',
            'primary_key' => 'id',
            'incremental_column' => 'updated_at',
            'allowed_columns' => ['id', 'email', 'api_key'],
            'excluded_columns' => [],
            'filters' => [],
            'row_limit' => 5000,
            'read_only' => true,
            'raw_sql' => 'select id, email from customers',
        ];

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/databases")
            ->post("http://{$domain}/connections/data-sources/{$dataSourceId}/database-config", $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('allowed_columns');

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/databases")
            ->post("http://{$domain}/connections/data-sources/{$dataSourceId}/database-config", [
                ...$payload,
                'excluded_columns' => ['api_key'],
                'raw_sql' => 'delete from customers',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('allowed_columns');
    }

    public function test_tenant_admin_can_register_discovered_mcp_tool_without_auto_enabling_it(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'MCP Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $integration = ConnectionIntegration::query()->where('key', 'mcp-server')->firstOrFail();
            $connection = Connection::create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $integration->id,
                'name' => 'Research MCP',
                'status' => ConnectionStatus::Active,
                'health_status' => ConnectionHealth::Healthy,
                'connection_type' => ConnectionType::McpServer,
                'auth_type' => AuthenticationType::None,
                'environment' => Environment::Production,
                'configuration' => ['server_url' => 'https://mcp.example.com/sse'],
                'credential_status' => CredentialStatus::Missing,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $connectionId = $connection->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/{$connectionId}/mcp-tools", [
                'key' => 'documents.search',
                'name' => 'Search documents',
                'description' => 'Search approved document indexes.',
                'risk_level' => 'medium',
                'input_schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
                'output_schema' => ['type' => 'object', 'properties' => []],
                'capabilities' => ['resource.read'],
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $tool = ConnectionAction::query()
                ->where('connection_id', $connectionId)
                ->where('key', 'documents.search')
                ->firstOrFail();

            $this->assertSame('mcp_tool', $tool->action_type);
            $this->assertFalse($tool->enabled_for_ai);
            $this->assertFalse($tool->enabled_for_workflows);
            $this->assertTrue(ConnectionLog::query()->where('event', 'mcp_tool.discovered')->where('connection_id', $connectionId)->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_mcp_tool_policy_requires_approval_for_high_risk_enablement(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'MCP Guard'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $integration = ConnectionIntegration::query()->where('key', 'mcp-server')->firstOrFail();
            $connection = Connection::create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $integration->id,
                'name' => 'Ops MCP',
                'status' => ConnectionStatus::Active,
                'health_status' => ConnectionHealth::Healthy,
                'connection_type' => ConnectionType::McpServer,
                'auth_type' => AuthenticationType::None,
                'environment' => Environment::Production,
                'configuration' => ['server_url' => 'https://ops-mcp.example.com/sse'],
                'credential_status' => CredentialStatus::Missing,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $tool = $connection->actions()->create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $integration->id,
                'key' => 'ticket.delete',
                'name' => 'Delete ticket',
                'action_type' => 'mcp_tool',
                'risk_level' => 'high',
                'requires_approval' => true,
                'enabled_for_ai' => false,
                'enabled_for_workflows' => false,
                'input_schema' => ['type' => 'object', 'properties' => ['ticket_id' => ['type' => 'string']]],
                'status' => 'active',
            ]);
            $connectionId = $connection->id;
            $toolId = $tool->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/mcp")
            ->put("http://{$domain}/connections/{$connectionId}/mcp-tools/{$toolId}", [
                'enabled_for_ai' => true,
                'enabled_for_workflows' => false,
                'requires_approval' => false,
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('enabled_for_ai');

        $this->actingAs($admin, 'tenant')
            ->put("http://{$domain}/connections/{$connectionId}/mcp-tools/{$toolId}", [
                'enabled_for_ai' => true,
                'enabled_for_workflows' => false,
                'requires_approval' => true,
                'status' => 'active',
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $tool = ConnectionAction::query()->findOrFail($toolId);
            $this->assertTrue($tool->enabled_for_ai);
            $this->assertTrue($tool->requires_approval);
            $this->assertTrue(ConnectionLog::query()->where('event', 'mcp_tool.enabled')->where('connection_id', $connectionId)->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_mcp_tool_discovery_rejects_unsafe_server_and_schema(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'MCP Unsafe'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $integration = ConnectionIntegration::query()->where('key', 'mcp-server')->firstOrFail();
            $connection = Connection::create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $integration->id,
                'name' => 'Unsafe MCP',
                'status' => ConnectionStatus::Active,
                'health_status' => ConnectionHealth::Healthy,
                'connection_type' => ConnectionType::McpServer,
                'auth_type' => AuthenticationType::None,
                'environment' => Environment::Production,
                'configuration' => ['server_url' => 'http://localhost:8787/sse'],
                'credential_status' => CredentialStatus::Missing,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $connectionId = $connection->id;
        } finally {
            tenancy()->end();
        }

        $payload = [
            'key' => 'unsafe.exec',
            'name' => 'Unsafe exec',
            'risk_level' => 'critical',
            'input_schema' => ['type' => 'object', 'properties' => []],
            'output_schema' => ['type' => 'object', 'properties' => []],
        ];

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/mcp")
            ->post("http://{$domain}/connections/{$connectionId}/mcp-tools", $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('key');

        tenancy()->initialize($tenant);

        try {
            Connection::query()->findOrFail($connectionId)->forceFill([
                'configuration' => ['server_url' => 'https://safe-mcp.example.com/sse'],
            ])->save();
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/mcp")
            ->post("http://{$domain}/connections/{$connectionId}/mcp-tools", [
                ...$payload,
                'input_schema' => ['type' => 'array'],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('key');
    }

    public function test_oauth_start_creates_short_lived_state_with_minimum_scopes_and_pkce(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'OAuth Admin'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $integration = ConnectionIntegration::query()->where('key', 'google-drive')->firstOrFail();
            $integrationId = $integration->id;
        } finally {
            tenancy()->end();
        }

        $response = $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/create")
            ->post("http://{$domain}/connections/oauth/start", [
                'connection_integration_id' => $integrationId,
                'scopes' => [],
                'redirect_path' => '/connections/create',
            ])
            ->assertRedirect()
            ->assertSessionHas('oauth_authorization');

        $authorization = $response->getSession()->get('oauth_authorization');

        $this->assertSame('S256', $authorization['code_challenge_method']);
        $this->assertContains('https://www.googleapis.com/auth/drive.readonly', $authorization['scopes']);

        tenancy()->initialize($tenant);

        try {
            $state = OAuthAuthorizationState::query()
                ->where('state_hash', hash('sha256', $authorization['state']))
                ->firstOrFail();

            $this->assertSame('/connections/create', $state->redirect_path);
            $this->assertContains('https://www.googleapis.com/auth/drive.readonly', $state->scopes);
            $this->assertTrue($state->expires_at->isFuture());
            $this->assertNotEmpty($state->code_challenge);
            $this->assertTrue(ConnectionLog::query()->where('event', 'oauth.authorization_started')->exists());
        } finally {
            tenancy()->end();
        }
    }

    public function test_oauth_start_rejects_excessive_scopes_and_unsafe_redirects(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'OAuth Guard'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $integrationId = ConnectionIntegration::query()->where('key', 'google-drive')->firstOrFail()->id;
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/create")
            ->post("http://{$domain}/connections/oauth/start", [
                'connection_integration_id' => $integrationId,
                'scopes' => ['https://www.googleapis.com/auth/drive'],
                'redirect_path' => '/connections/create',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('scopes');

        $this->actingAs($admin, 'tenant')
            ->from("http://{$domain}/connections/create")
            ->post("http://{$domain}/connections/oauth/start", [
                'connection_integration_id' => $integrationId,
                'scopes' => ['https://www.googleapis.com/auth/drive.readonly'],
                'redirect_path' => 'https://evil.test/callback',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('redirect_path');
    }

    public function test_oauth_callback_consumes_state_once_and_logs_provider_errors(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'OAuth Callback'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $integrationId = ConnectionIntegration::query()->where('key', 'google-drive')->firstOrFail()->id;
        } finally {
            tenancy()->end();
        }

        $response = $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/connections/oauth/start", [
                'connection_integration_id' => $integrationId,
                'scopes' => ['https://www.googleapis.com/auth/drive.readonly'],
                'redirect_path' => '/connections',
            ])
            ->assertRedirect()
            ->assertSessionHas('oauth_authorization');

        $state = $response->getSession()->get('oauth_authorization')['state'];

        $this->actingAs($admin, 'tenant')
            ->get("http://{$domain}/connections/oauth/callback?state={$state}&error=access_denied&error_description=Denied")
            ->assertRedirect('/connections')
            ->assertSessionHas('error');

        tenancy()->initialize($tenant);

        try {
            $this->assertNotNull(OAuthAuthorizationState::query()->where('state_hash', hash('sha256', $state))->value('consumed_at'));
            $this->assertTrue(ConnectionLog::query()->where('event', 'oauth.authorization_failed')->exists());
        } finally {
            tenancy()->end();
        }

        $this->actingAs($admin, 'tenant')
            ->get("http://{$domain}/connections/oauth/callback?state={$state}")
            ->assertRedirect('/connections')
            ->assertSessionHas('error');
    }

    public function test_tenant_api_connection_usage_endpoint_returns_billing_rollups(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Usage API'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $integration = ConnectionIntegration::query()->where('key', 'custom-rest')->firstOrFail();
            $connection = Connection::create([
                'tenant_id' => tenant('id'),
                'connection_integration_id' => $integration->id,
                'name' => 'Usage API connection',
                'status' => ConnectionStatus::Active,
                'health_status' => ConnectionHealth::Healthy,
                'connection_type' => ConnectionType::Api,
                'auth_type' => AuthenticationType::None,
                'environment' => Environment::Production,
                'configuration' => ['base_url' => 'https://93.184.216.34'],
                'credential_status' => CredentialStatus::Missing,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $connectionId = $connection->id;
            $token = $this->createDeveloperToken(['reports.read'], $admin->id);

            app(ConnectionUsageService::class)->record(
                'action_execution',
                quantity: 3,
                connection: $connection,
                metadata: ['action' => 'search_contacts', 'api_key' => 'secret-value'],
            );
            app(ConnectionUsageService::class)->record(
                'sync_items',
                quantity: 15,
                unit: 'records',
                bytes: 4096,
                connection: $connection,
                metadata: ['source' => 'knowledge'],
            );
        } finally {
            tenancy()->end();
        }

        $this->withToken($token)
            ->getJson("http://{$domain}/tenant-api/v1/connections/{$connectionId}/usage?from=".today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.totals.events', 2)
            ->assertJsonPath('data.totals.quantity', 18)
            ->assertJsonPath('data.billing_categories.ai_action_usage.quantity', 3)
            ->assertJsonPath('data.billing_categories.knowledge_base_usage.quantity', 15)
            ->assertJsonPath('data.by_usage_type.action_execution.quantity', 3)
            ->assertJsonFragment(['api_key' => '[redacted]']);
    }

    public function test_tenant_api_connection_usage_endpoint_requires_reports_scope(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant, ['name' => 'Usage Scope'], 'Tenant Administrator');

        tenancy()->initialize($tenant);

        try {
            $connectionId = Connection::query()->where('status', ConnectionStatus::Active->value)->firstOrFail()->id;
            $token = $this->createDeveloperToken(['contacts.read'], $admin->id);
        } finally {
            tenancy()->end();
        }

        $this->withToken($token)
            ->getJson("http://{$domain}/tenant-api/v1/connections/{$connectionId}/usage")
            ->assertForbidden();
    }

    private function createDeveloperToken(array $scopes, int $userId): string
    {
        $plain = 'pb_'.Str::random(48);

        DB::table('developer_api_keys')->insert([
            'public_uuid' => (string) Str::uuid(),
            'name' => 'Connections usage test',
            'token_prefix' => substr($plain, 0, 11),
            'token_hash' => hash('sha256', $plain),
            'scopes' => json_encode($scopes),
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $plain;
    }

    private function createSignedWebhookEndpoint(string $secret, array $eventTypes): WebhookEndpoint
    {
        $integration = ConnectionIntegration::query()->firstOrFail();
        $connection = Connection::create([
            'tenant_id' => tenant('id'),
            'connection_integration_id' => $integration->id,
            'name' => 'Signed webhook test',
            'status' => ConnectionStatus::Active,
            'health_status' => ConnectionHealth::Healthy,
            'connection_type' => ConnectionType::Webhook,
            'auth_type' => AuthenticationType::None,
            'environment' => Environment::Production,
            'credential_status' => CredentialStatus::Missing,
        ]);

        return WebhookEndpoint::create([
            'tenant_id' => tenant('id'),
            'connection_id' => $connection->id,
            'name' => 'Signed inbound test',
            'provider' => 'custom',
            'status' => 'active',
            'endpoint_path' => 'signed-'.Str::random(16),
            'signature_algorithm' => 'hmac_sha256',
            'encrypted_secret' => $secret,
            'event_types' => $eventTypes,
            'configuration' => ['replay_window_seconds' => 300],
        ]);
    }

    private function webhookSignature(string $payload, string $secret, string $timestamp): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }
}
