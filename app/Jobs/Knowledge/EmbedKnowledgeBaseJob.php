<?php

namespace App\Jobs\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\ProcessingStage;
use App\Events\Knowledge\KnowledgeProcessingCompleted;
use App\Jobs\Concerns\TenantAware;
use App\Jobs\Middleware\InitializeTenantContext;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeChunk;
use App\Models\Knowledge\KnowledgeDocument;
use App\Services\Knowledge\EmbeddingService;
use App\Services\Knowledge\KnowledgeStatisticsService;
use App\Services\Knowledge\ProcessingJobTracker;
use App\Services\Knowledge\ProcessingStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Embeds a knowledge base's pending chunks.
 *
 * Uniqueness and mutual exclusion are split across two mechanisms on purpose:
 *
 *   ShouldBeUniqueUntilProcessing  de-duplicates DISPATCHES. Uploading twenty
 *     files at once queues twenty EmbedKnowledgeBaseJob calls; only the first
 *     is actually enqueued. Unlike plain ShouldBeUnique, this lock is released
 *     the moment the job starts running rather than held for its full
 *     duration — so a continuation this job dispatches on itself mid-run (more
 *     pending chunks than the batch limit) is never silently dropped by its
 *     own still-held lock, which is exactly what happened before this fix.
 *
 *   WithoutOverlapping (in middleware())  de-duplicates EXECUTION. If a second
 *     copy for the same base is ever enqueued while the first is still
 *     running, it is released back onto the queue with a short delay instead
 *     of running concurrently — so two workers never embed the same base's
 *     backlog at once, and a duplicate copy is recoverable rather than lost.
 */
class EmbedKnowledgeBaseJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 5;

    public int $timeout = 900;

    /** How long the dispatch-time uniqueness lock survives if it is never picked up. */
    public int $uniqueFor = 1800;

    public function __construct(private readonly int $knowledgeBaseId)
    {
        $this->captureTenant();
        $this->onQueue(config('knowledge.queues.embedding'));
    }

    /**
     * Scoped by tenant as well as base: base #4 in two different workspaces are
     * different work, and a shared lock key would let one tenant's job suppress
     * another's.
     */
    public function uniqueId(): string
    {
        return ($this->tenantId ?? 'central').':kb:'.$this->knowledgeBaseId;
    }

    public function middleware(): array
    {
        return [
            new InitializeTenantContext($this->tenantId),
            (new WithoutOverlapping($this->uniqueId()))->releaseAfter(5)->expireAfter($this->timeout + 60),
        ];
    }

    public function backoff(): array
    {
        return [30, 120, 300, 900, 1800];
    }

    public function handle(
        EmbeddingService $embeddings,
        KnowledgeStatisticsService $statistics,
        ProcessingStateMachine $states,
        ProcessingJobTracker $tracker,
    ): void {
        $base = KnowledgeBase::find($this->knowledgeBaseId);

        if (! $base) {
            return;
        }

        $result = $embeddings->embedPending($base, limit: 2000);

        $statistics->refreshForBase($base);

        if ($result['embedded'] > 0) {
            $base->forceFill(['last_indexed_at' => now()])->save();
        }

        $this->markDocumentsReady($base->id, $states, $tracker);

        // Anything still pending means the batch limit was hit, not that
        // embedding failed. Re-dispatch so the backlog drains rather than
        // stalling until the next unrelated upload. Dispatched after
        // finalizing this run's documents so a document that just finished
        // is never re-evaluated by the continuation.
        if ($embeddings->pendingCount($base) > 0) {
            self::dispatch($this->knowledgeBaseId)->delay(now()->addSeconds(5));
        }
    }

    /**
     * The job exhausted every retry — e.g. the embedding provider is
     * permanently misconfigured, so every attempt fails the same way.
     * Documents still sitting in `embedding` for this base would otherwise
     * stay there forever, since nothing else ever revisits them once the
     * queue driver gives up. Move them to a failed, retryable state instead,
     * and make the real reason visible in the application log — this is the
     * one path in the module that is not wrapped by ProcessingLogger, since
     * there is no single document or job row this failure belongs to.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('knowledge.embedding_job_failed', array_filter([
            'tenant_id' => $this->tenantId,
            'knowledge_base_id' => $this->knowledgeBaseId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]));

        $this->runInTenantContext(function (): void {
            $states = app(ProcessingStateMachine::class);
            $tracker = app(ProcessingJobTracker::class);

            KnowledgeDocument::query()
                ->where('knowledge_base_id', $this->knowledgeBaseId)
                ->where('status', DocumentStatus::Embedding->value)
                ->get(['id', 'status', 'current_stage', 'retry_count'])
                ->each(function (KnowledgeDocument $document) use ($states, $tracker): void {
                    $states->fail(
                        $document,
                        ProcessingStage::Embedding,
                        FailureCategory::EmbeddingProviderError,
                        'Embedding failed repeatedly and stopped retrying. Check the provider configuration, then retry this document.',
                    );

                    $tracker->finalizeForDocument($document);
                });
        });
    }

    /**
     * Promotes documents whose chunks have all been resolved (embedded or
     * permanently failed) and finalizes each one's operator-visible
     * processing job independently.
     */
    private function markDocumentsReady(int $knowledgeBaseId, ProcessingStateMachine $states, ProcessingJobTracker $tracker): void
    {
        $documents = KnowledgeDocument::query()
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->where('status', DocumentStatus::Embedding->value)
            ->get(['id', 'uuid', 'status', 'chunk_count', 'processing_started_at', 'current_stage']);

        foreach ($documents as $document) {
            $counts = KnowledgeChunk::query()
                ->where('owner_key', $document->chunkOwnerKey())
                ->selectRaw(
                    'count(*) as total,'
                    .' sum(case when embedding_status = ? then 1 else 0 end) as ready,'
                    .' sum(case when embedding_status = ? then 1 else 0 end) as pending',
                    [KnowledgeChunk::EMBEDDING_READY, KnowledgeChunk::EMBEDDING_PENDING]
                )
                ->first();

            // Still work outstanding — leave it in `embedding`.
            if ((int) $counts->pending > 0) {
                continue;
            }

            $states->transition($document, DocumentStatus::Indexing, ProcessingStage::Indexing);
            // complete() mutates $document in place (via ProcessingStateMachine::
            // transition()'s forceFill), so it already reflects the final
            // status — ready, partially_ready, or failed — by the time the
            // tracker reads it below.
            $states->complete($document, (int) $counts->total, (int) $counts->ready);

            $tracker->finalizeForDocument($document);

            KnowledgeProcessingCompleted::dispatch($document->id, (int) $counts->total);
        }
    }
}
