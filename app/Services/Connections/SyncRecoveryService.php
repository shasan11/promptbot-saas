<?php

namespace App\Services\Connections;

use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Enums\Connections\SyncStatus;
use App\Jobs\Connections\RunConnectionSyncJob;
use App\Models\Connections\SyncRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SyncRecoveryService
{
    public function __construct(
        private readonly RetryPolicyService $retryPolicy,
        private readonly ConnectionAuditService $audit,
    ) {}

    public function retry(SyncRun $syncRun, ?User $actor = null): SyncRun
    {
        $syncRun->loadMissing(['connection', 'dataSource']);

        if (! $syncRun->connection) {
            throw new InvalidArgumentException('Cannot retry a sync whose connection was deleted.');
        }

        if (! in_array($syncRun->status, [
            SyncStatus::Failed,
            SyncStatus::CompletedWithErrors,
            SyncStatus::RateLimited,
            SyncStatus::WaitingForAuth,
        ], true)) {
            throw new InvalidArgumentException('Only failed or interrupted sync runs can be retried.');
        }

        $category = $this->retryPolicy->classify(errorCode: $syncRun->error_code);
        $attempt = $syncRun->retry_count + 1;

        if (! $this->retryPolicy->shouldRetry($category, $attempt)) {
            throw new InvalidArgumentException('This sync failure requires manual repair before retrying.');
        }

        return DB::transaction(function () use ($syncRun, $actor, $category, $attempt): SyncRun {
            $syncRun->forceFill([
                'status' => SyncStatus::Retrying,
                'retry_count' => $attempt,
                'error_summary' => trim(($syncRun->error_summary ? $syncRun->error_summary.' ' : '').'Retry queued.'),
            ])->save();

            $connection = $syncRun->connection;
            $connection->forceFill([
                'status' => $connection->status === ConnectionStatus::RateLimited ? ConnectionStatus::Degraded : $connection->status,
                'health_status' => $connection->health_status === ConnectionHealth::RateLimited ? ConnectionHealth::Degraded : $connection->health_status,
                'last_error_code' => $syncRun->error_code,
                'last_error_message' => 'Retry queued for failed sync.',
                'last_error_at' => now(),
            ])->save();

            $this->audit->record('sync.retry_queued', $connection, $actor, message: 'Failed sync retry queued.', context: [
                'sync_run_id' => $syncRun->id,
                'data_source_id' => $syncRun->data_source_id,
                'category' => $category->value,
                'retry_count' => $attempt,
                'backoff_seconds' => $this->retryPolicy->backoffSeconds($attempt),
            ], dataSource: $syncRun->dataSource, syncRun: $syncRun, level: 'warning');

            RunConnectionSyncJob::dispatch(
                $connection->id,
                $syncRun->data_source_id,
                $actor?->id,
                'retry',
            )->delay(now()->addSeconds($this->retryPolicy->backoffSeconds($attempt)));

            return $syncRun;
        });
    }
}
