<?php

namespace App\Jobs\Knowledge;

use App\Enums\Knowledge\ProcessingJobStatus;
use App\Jobs\Concerns\TenantAware;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\Knowledge\KnowledgeSyncRun;
use App\Services\Knowledge\KnowledgeSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Re-fetches a source (currently: crawls a website) and queues any changed
 * content for processing.
 *
 * Unique per source so a scheduled run and an impatient "Sync now" click cannot
 * crawl the same site simultaneously — which would double the load we put on
 * someone else's server and race each other's page writes.
 *
 * Runs on its own queue: a 5,000-page crawl must not sit in front of a user
 * waiting to re-index one FAQ.
 */
class SyncKnowledgeSourceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 2;

    public int $timeout = 3600;

    public int $uniqueFor = 7200;

    public function __construct(
        private readonly int $sourceId,
        private readonly string $trigger = KnowledgeSyncRun::TRIGGER_SCHEDULED,
        private readonly ?int $actorId = null,
    ) {
        $this->captureTenant();
        $this->onQueue(config('knowledge.queues.crawl'));
    }

    public function uniqueId(): string
    {
        return ($this->tenantId ?? 'central').':source:'.$this->sourceId;
    }

    public function handle(KnowledgeSyncService $sync): void
    {
        $source = KnowledgeSource::find($this->sourceId);

        if (! $source) {
            return;
        }

        $run = $sync->startRun($source, $this->trigger, $this->actorId ? \App\Models\User::find($this->actorId) : null);

        $job = KnowledgeProcessingJob::create([
            'knowledge_base_id' => $source->knowledge_base_id,
            'knowledge_source_id' => $source->id,
            'job_type' => KnowledgeProcessingJob::TYPE_CRAWL,
            'queue' => $this->queue,
            'status' => ProcessingJobStatus::Running->value,
            'queued_at' => now(),
            'started_at' => now(),
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'created_by' => $this->actorId,
        ]);

        try {
            $stats = $sync->run($source, $run, $job);

            $job->forceFill([
                'status' => ProcessingJobStatus::Completed->value,
                'progress' => 100,
                'items_total' => $stats['discovered'] ?? 0,
                'items_processed' => ($stats['created'] ?? 0) + ($stats['updated'] ?? 0) + ($stats['unchanged'] ?? 0),
                'items_failed' => $stats['failed'] ?? 0,
                'finished_at' => now(),
            ])->save();

            // Only pages whose content actually changed produced a queued
            // document; unchanged pages were skipped entirely and cost nothing.
            $this->dispatchProcessingForChangedPages($source);
        } catch (Throwable $e) {
            $job->forceFill([
                'status' => ProcessingJobStatus::Failed->value,
                'last_error' => 'Synchronisation failed.',
                'finished_at' => now(),
            ])->save();

            throw $e;
        }
    }

    private function dispatchProcessingForChangedPages(KnowledgeSource $source): void
    {
        \App\Models\Knowledge\KnowledgeDocument::query()
            ->where('knowledge_source_id', $source->id)
            ->where('status', \App\Enums\Knowledge\DocumentStatus::Queued->value)
            ->orderBy('id')
            ->chunkById(200, function ($documents): void {
                foreach ($documents as $document) {
                    ProcessKnowledgeDocumentJob::dispatch($document->id);
                }
            });
    }
}
