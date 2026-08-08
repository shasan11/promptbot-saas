<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\ProcessingStage;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Enums\Knowledge\SyncStatus;
use App\Events\Knowledge\KnowledgeSourceSynced;
use App\Exceptions\Knowledge\KnowledgeException;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\Knowledge\KnowledgeSyncRun;
use App\Models\User;
use App\Services\Knowledge\Crawler\WebsiteCrawlerService;
use Throwable;

/**
 * Orchestrates a source refresh and records what changed.
 *
 * The retry policy lives here rather than in the job, because whether a failure
 * is worth retrying is a domain question: an expired OAuth token will fail
 * identically forever and should park the source for a human, while a timeout
 * should back off and try again.
 */
class KnowledgeSyncService
{
    public function __construct(
        private readonly WebsiteCrawlerService $crawler,
        private readonly ProcessingLogger $logger,
        private readonly KnowledgeStatisticsService $statistics,
    ) {}

    public function startRun(KnowledgeSource $source, string $trigger, ?User $actor = null): KnowledgeSyncRun
    {
        $source->forceFill([
            'sync_status' => SyncStatus::Running->value,
            'last_synced_at' => now(),
        ])->save();

        return KnowledgeSyncRun::create([
            'knowledge_source_id' => $source->id,
            'trigger' => $trigger,
            'status' => SyncStatus::Running->value,
            'started_at' => now(),
            'triggered_by' => $actor?->id,
        ]);
    }

    /**
     * Runs the sync appropriate to the source type.
     *
     * @return array<string, int>
     */
    public function run(KnowledgeSource $source, KnowledgeSyncRun $run, ?KnowledgeProcessingJob $job = null): array
    {
        $startedAt = hrtime(true);

        try {
            $stats = match (true) {
                $source->source_type->isCrawlable() => $this->crawler->crawl($source, $run, $job),
                default => throw new KnowledgeException(
                    "No sync handler for source type [{$source->source_type->value}]",
                    FailureCategory::Unknown,
                    'This source type cannot be synchronised yet.',
                ),
            };

            $this->completeRun($source, $run, $stats, (int) ((hrtime(true) - $startedAt) / 1_000_000));

            return $stats;
        } catch (Throwable $e) {
            $this->failRun($source, $run, $e, (int) ((hrtime(true) - $startedAt) / 1_000_000));

            throw $e;
        }
    }

    /** @param  array<string, int>  $stats */
    private function completeRun(KnowledgeSource $source, KnowledgeSyncRun $run, array $stats, int $durationMs): void
    {
        $failed = (int) ($stats['failed'] ?? 0);
        $status = $failed > 0 ? SyncStatus::CompletedWithErrors : SyncStatus::Completed;

        $run->forceFill([
            'status' => $status->value,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
            'items_discovered' => $stats['discovered'] ?? 0,
            'items_created' => $stats['created'] ?? 0,
            'items_updated' => $stats['updated'] ?? 0,
            'items_unchanged' => $stats['unchanged'] ?? 0,
            'items_deleted' => $stats['missing'] ?? 0,
            'items_skipped' => $stats['skipped'] ?? 0,
            'items_failed' => $failed,
            'summary' => $stats,
        ])->save();

        $source->forceFill([
            'sync_status' => $status->value,
            'last_synced_at' => now(),
            'last_successful_sync_at' => now(),
            'next_sync_at' => $source->sync_frequency->nextRunAfter(now()),
            'consecutive_failure_count' => 0,
            'last_error' => null,
            'last_failure_stage' => null,
            'last_failure_category' => null,
            'review_due_at' => $source->review_every_days
                ? now()->addDays($source->review_every_days)
                : null,
        ])->save();

        $this->statistics->refreshForSource($source);

        KnowledgeSourceSynced::dispatch($source->id, $run->id, $stats, true);
    }

    private function failRun(KnowledgeSource $source, KnowledgeSyncRun $run, Throwable $exception, int $durationMs): void
    {
        $failure = $this->logger->failure(
            ProcessingStage::Extracting,
            $exception,
            null,
            $source,
            $source->consecutive_failure_count + 1,
        );

        $run->forceFill([
            'status' => SyncStatus::Failed->value,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
            'last_error' => $failure->message,
        ])->save();

        $failures = $source->consecutive_failure_count + 1;

        // A source that needs a human (revoked credentials, exhausted quota) is
        // parked rather than rescheduled. So is one that has failed repeatedly:
        // an hourly sync that has failed twelve times in a row is not going to
        // succeed on the thirteenth, it is just burning worker capacity and
        // filling the failures table.
        $needsAttention = $failure->category->requiresAttention() || $failures >= 5;

        $source->forceFill([
            'sync_status' => SyncStatus::Failed->value,
            'status' => $needsAttention ? SourceStatus::AttentionRequired->value : $source->status->value,
            'consecutive_failure_count' => $failures,
            'last_error' => $failure->message,
            'last_failure_stage' => ProcessingStage::Extracting->value,
            'last_failure_category' => $failure->category->value,
            'next_sync_at' => $needsAttention ? null : $this->backoffNextRun($source, $failures),
        ])->save();

        KnowledgeSourceSynced::dispatch($source->id, $run->id, [], false);
    }

    /**
     * Exponential backoff on the *schedule*, so a flapping source degrades to
     * checking occasionally rather than hammering a broken endpoint at its
     * configured frequency.
     */
    private function backoffNextRun(KnowledgeSource $source, int $failures): ?\Carbon\CarbonInterface
    {
        $interval = $source->sync_frequency->intervalMinutes();

        if ($interval === null) {
            return null;
        }

        $multiplier = min(2 ** ($failures - 1), 24);

        return now()->addMinutes($interval * $multiplier);
    }

    /**
     * Sources whose schedule is due. Ordered oldest-first so a backlog drains
     * fairly rather than starving whichever source sorts last by id.
     *
     * @return \Illuminate\Support\Collection<int, KnowledgeSource>
     */
    public function dueSources(int $limit = 50): \Illuminate\Support\Collection
    {
        return KnowledgeSource::query()
            ->dueForSync()
            ->orderBy('next_sync_at')
            ->limit($limit)
            ->get();
    }

    /** Schedules the first run when a syncable source is created or its frequency changes. */
    public function scheduleNextRun(KnowledgeSource $source): void
    {
        if (! $source->source_type->isSyncable()) {
            return;
        }

        $source->forceFill([
            'next_sync_at' => $source->sync_frequency->nextRunAfter($source->last_successful_sync_at ?? now()),
        ])->save();
    }
}
