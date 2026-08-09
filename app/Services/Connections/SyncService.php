<?php

namespace App\Services\Connections;

use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Enums\Connections\SyncStatus;
use App\Models\Connections\Connection;
use App\Models\Connections\DataSource;
use App\Models\Connections\SyncRun;
use App\Models\Connections\SyncRunItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SyncService
{
    public function __construct(
        private readonly ConnectorManager $connectors,
        private readonly ConnectionAuditService $audit,
        private readonly SyncLockService $locks,
        private readonly ProviderRateLimitService $rateLimits,
        private readonly ConnectionResourcePermissionService $resourcePermissions,
    ) {}

    public function run(Connection $connection, ?DataSource $dataSource = null, ?User $actor = null, string $trigger = 'manual'): SyncRun
    {
        $lock = $this->locks->acquire($connection, $dataSource);

        try {
            if ($dataSource) {
                try {
                    $this->resourcePermissions->assertDataSourceSyncAllowed($dataSource, $actor);
                } catch (InvalidArgumentException $exception) {
                    return $this->failForResourcePermission($connection, $dataSource, $actor, $trigger, $exception->getMessage());
                }
            }

            if ($rateLimit = $this->rateLimits->activeBackoff($connection)) {
                return $this->pauseForRateLimit($connection, $dataSource, $actor, $trigger, $rateLimit);
            }

            return DB::transaction(function () use ($connection, $dataSource, $actor, $trigger): SyncRun {
            $started = now();
            $run = SyncRun::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'data_source_id' => $dataSource?->id,
                'sync_type' => $dataSource?->sync_mode?->value ?? 'manual',
                'status' => SyncStatus::Running,
                'started_at' => $started,
                'cursor_before' => $dataSource?->last_cursor,
                'triggered_by' => $actor?->id,
                'trigger_source' => $trigger,
            ]);

            $this->audit->record('sync.started', $connection, $actor, dataSource: $dataSource, syncRun: $run);
            $result = $this->connectors->for($connection)->sync($connection, $dataSource?->id);
            $completed = now();
            $rateLimit = $this->recordProviderRateLimit($connection, $result);

            $run->forceFill([
                'status' => SyncStatus::Completed,
                'completed_at' => $completed,
                'duration_ms' => max(1, $completed->diffInMilliseconds($started)),
                'cursor_after' => $result['cursor_after'] ?? null,
                'items_discovered' => $result['items_discovered'] ?? 0,
                'items_created' => $result['items_created'] ?? 0,
                'items_updated' => $result['items_updated'] ?? 0,
                'items_deleted' => $result['items_deleted'] ?? 0,
                'items_skipped' => $result['items_skipped'] ?? 0,
                'items_failed' => $result['items_failed'] ?? 0,
                'bytes_received' => $result['bytes_received'] ?? 0,
                'api_requests' => $result['api_requests'] ?? 0,
            ])->save();

            foreach ($this->sampleItems($result) as $item) {
                SyncRunItem::updateOrCreate(
                    ['sync_run_id' => $run->id, 'external_id' => $item['external_id'], 'operation' => $item['operation']],
                    [
                        'tenant_id' => tenant('id'),
                        'data_source_id' => $dataSource?->id,
                        'status' => $item['status'],
                        'content_hash' => $item['content_hash'] ?? null,
                        'metadata' => $item['metadata'] ?? [],
                    ]
                );
            }

            if ($dataSource) {
                $dataSource->forceFill([
                    'last_synced_at' => $completed,
                    'last_successful_sync_at' => $completed,
                    'last_cursor' => $result['cursor_after'] ?? $dataSource->last_cursor,
                    'records_synced' => $dataSource->records_synced + ($result['items_created'] ?? 0) + ($result['items_updated'] ?? 0),
                    'bytes_synced' => $dataSource->bytes_synced + ($result['bytes_received'] ?? 0),
                ])->save();
            }

            $connection->forceFill([
                'status' => $rateLimit?->backoff_until?->isFuture() ? ConnectionStatus::RateLimited : ConnectionStatus::Active,
                'health_status' => $rateLimit?->backoff_until?->isFuture() ? ConnectionHealth::RateLimited : ConnectionHealth::Healthy,
                'last_successful_check_at' => $completed,
            ])->save();

            $this->audit->record('sync.completed', $connection, $actor, message: 'Sync completed.', context: $result, dataSource: $dataSource, syncRun: $run);

            return $run;
            });
        } finally {
            optional($lock)->release();
        }
    }

    private function sampleItems(array $result): array
    {
        $count = min(5, (int) (($result['items_created'] ?? 0) + ($result['items_updated'] ?? 0)));

        return collect(range(1, max(1, $count)))->map(fn (int $index) => [
            'external_id' => 'sync-item-'.$index,
            'operation' => $index === 1 ? 'create' : 'update',
            'status' => 'processed',
            'content_hash' => hash('sha256', json_encode([$result, $index])),
            'metadata' => ['sampled' => true],
        ])->all();
    }

    private function pauseForRateLimit(Connection $connection, ?DataSource $dataSource, ?User $actor, string $trigger, $rateLimit): SyncRun
    {
        $existing = $connection->syncRuns()
            ->where('status', SyncStatus::RateLimited->value)
            ->where('error_code', 'RATE_LIMITED')
            ->when($dataSource, fn ($query) => $query->where('data_source_id', $dataSource->id), fn ($query) => $query->whereNull('data_source_id'))
            ->when($rateLimit->observed_at, fn ($query) => $query->where('started_at', '>=', $rateLimit->observed_at->copy()->subSecond()))
            ->latest('started_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($connection, $dataSource, $actor, $trigger, $rateLimit): SyncRun {
            $now = now();
            $seconds = $this->rateLimits->backoffSeconds($rateLimit);
            $message = 'Sync paused because the provider is rate limited. Retry after '.$rateLimit->backoff_until?->toDateTimeString().'.';

            $run = SyncRun::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'data_source_id' => $dataSource?->id,
                'sync_type' => $dataSource?->sync_mode?->value ?? 'manual',
                'status' => SyncStatus::RateLimited,
                'started_at' => $now,
                'completed_at' => $now,
                'duration_ms' => 1,
                'cursor_before' => $dataSource?->last_cursor,
                'retry_count' => 0,
                'error_code' => 'RATE_LIMITED',
                'error_summary' => $message,
                'triggered_by' => $actor?->id,
                'trigger_source' => $trigger,
            ]);

            $connection->forceFill([
                'status' => ConnectionStatus::RateLimited,
                'health_status' => ConnectionHealth::RateLimited,
                'last_error_at' => $now,
                'last_error_code' => 'RATE_LIMITED',
                'last_error_message' => $message,
            ])->save();

            $this->audit->record('sync.rate_limited', $connection, $actor, 'rate_limited', $message, [
                'provider' => $rateLimit->provider,
                'bucket' => $rateLimit->bucket,
                'remaining' => $rateLimit->remaining,
                'limit' => $rateLimit->limit,
                'resets_at' => $rateLimit->resets_at?->toIso8601String(),
                'backoff_until' => $rateLimit->backoff_until?->toIso8601String(),
                'backoff_seconds' => $seconds,
            ], $dataSource, $run, 'warning');

            return $run;
        });
    }

    private function failForResourcePermission(Connection $connection, DataSource $dataSource, ?User $actor, string $trigger, string $message): SyncRun
    {
        return DB::transaction(function () use ($connection, $dataSource, $actor, $trigger, $message): SyncRun {
            $now = now();

            $run = SyncRun::create([
                'tenant_id' => tenant('id'),
                'connection_id' => $connection->id,
                'data_source_id' => $dataSource->id,
                'sync_type' => $dataSource->sync_mode?->value ?? 'manual',
                'status' => SyncStatus::Failed,
                'started_at' => $now,
                'completed_at' => $now,
                'duration_ms' => 1,
                'cursor_before' => $dataSource->last_cursor,
                'error_code' => 'RESOURCE_PERMISSION_DENIED',
                'error_summary' => $message,
                'triggered_by' => $actor?->id,
                'trigger_source' => $trigger,
            ]);

            $connection->forceFill([
                'status' => ConnectionStatus::NeedsAttention,
                'health_status' => ConnectionHealth::NeedsAttention,
                'last_error_at' => $now,
                'last_error_code' => 'RESOURCE_PERMISSION_DENIED',
                'last_error_message' => $message,
            ])->save();

            $this->audit->record('sync.resource_permission_denied', $connection, $actor, 'denied', $message, [
                'resource_id' => $dataSource->connection_resource_id,
                'data_source_id' => $dataSource->id,
            ], $dataSource, $run, 'warning');

            return $run;
        });
    }

    private function recordProviderRateLimit(Connection $connection, array $result)
    {
        $headers = $result['rate_limit_headers'] ?? $result['provider_headers'] ?? $result['headers'] ?? [];

        if (! is_array($headers) || $headers === []) {
            return null;
        }

        return $this->rateLimits->record($connection, $headers, (string) ($result['rate_limit_bucket'] ?? 'default'));
    }
}
