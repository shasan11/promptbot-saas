<?php

namespace Tests\Feature\Tenant\Connections;

use App\Enums\Connections\AuthenticationType;
use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Enums\Connections\CredentialStatus;
use App\Enums\Connections\SyncStatus;
use App\Jobs\Connections\RunConnectionSyncJob;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionAction;
use App\Models\Connections\ConnectionCredential;
use App\Models\Connections\ConnectionIdempotencyKey;
use App\Models\Connections\ConnectionIntegration;
use App\Models\Connections\ConnectionLog;
use App\Models\Connections\CredentialRotation;
use App\Models\Connections\DataSource;
use App\Models\Connections\SyncRun;
use App\Models\Connections\WebhookDeliveryAttempt;
use App\Models\Connections\WebhookEvent;
use App\Models\Connections\WebhookEndpoint;
use App\Services\Connections\ConnectionActionExecutionService;
use App\Services\Connections\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
}
