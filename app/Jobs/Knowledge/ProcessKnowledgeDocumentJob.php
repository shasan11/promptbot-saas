<?php

namespace App\Jobs\Knowledge;

use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\ProcessingJobStatus;
use App\Enums\Knowledge\ProcessingStage;
use App\Exceptions\Knowledge\KnowledgeException;
use App\Jobs\Concerns\TenantAware;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Services\Knowledge\DocumentProcessingService;
use App\Services\Knowledge\ProcessingJobTracker;
use App\Services\Knowledge\ProcessingStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs one document through the extraction pipeline.
 *
 * Only the document ID travels with the job. Serialising the model would carry
 * a megabyte of extracted text through the queue payload, and would restore it
 * against whatever connection is active when the worker picks it up — which, in
 * database-per-tenant mode, is only correct after the TenantAware middleware has
 * switched connections. Loading by ID inside handle() is both smaller and safer.
 */
class ProcessKnowledgeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(
        private readonly int $documentId,
        private readonly ?int $processingJobId = null,
        private readonly bool $force = false,
    ) {
        $this->captureTenant();
        $this->onQueue(config('knowledge.queues.processing'));
    }

    /** Exponential backoff, so a transient provider outage is not retried instantly three times. */
    public function backoff(): array
    {
        return (array) config('knowledge.processing.backoff', [60, 300, 900]);
    }

    public function handle(
        DocumentProcessingService $processor,
        ProcessingStateMachine $states,
        ProcessingJobTracker $tracker,
    ): void {
        $document = KnowledgeDocument::find($this->documentId);

        // The document may have been deleted between dispatch and execution.
        // That is a normal outcome, not a failure.
        if (! $document) {
            return;
        }

        $job = $this->processingJobId ? KnowledgeProcessingJob::find($this->processingJobId) : null;

        $job?->forceFill([
            'status' => ProcessingJobStatus::Running->value,
            'started_at' => $job->started_at ?? now(),
            'attempt' => $this->attempts(),
        ])->save();

        try {
            $result = $processor->process($document, $job, $this->force);

            // Last cooperative cancellation checkpoint: extraction/chunking is
            // done and about to hand off to the embedding job. Catching it
            // here avoids dispatching embedding work for a document the
            // operator no longer wants processed.
            if ($result['status'] === 'awaiting_embedding' && $job?->isCancelled()) {
                $states->cancel($document);
                $tracker->markCancelled($job, 'Cancelled by operator before embedding started.');

                return;
            }

            match ($result['status']) {
                // Another attempt already owns this document — not a failure,
                // just nothing for this job to finalize.
                'skipped_not_owned' => $job ? $tracker->markStoodDown($job) : null,
                'cancelled' => $job ? $tracker->markCancelled($job, 'Cancelled by operator.') : null,
                // Extraction/chunking finished but chunks are still awaiting a
                // vector: the ingestion is NOT done, so the job stays running
                // rather than being reported complete. EmbedKnowledgeBaseJob
                // finalizes this same row once embedding actually finishes.
                'awaiting_embedding' => $job ? $tracker->markAwaitingEmbedding($job, $result['chunks']) : null,
                default => $job ? $tracker->markCompleted($job, $result['chunks']) : null,
            };

            // Chunks needing a vector are embedded by a separate job so that a
            // rate limit retries only the embedding, not the extraction.
            if ($result['status'] === 'awaiting_embedding') {
                EmbedKnowledgeBaseJob::dispatch($document->knowledge_base_id);
            }
        } catch (KnowledgeException $e) {
            $this->handleFailure($e, $e->isRetryable(), $e->operatorMessage(), $document, $job, $states);
        } catch (Throwable $e) {
            $this->handleFailure($e, true, 'Processing failed unexpectedly.', $document, $job, $states);
        }
    }

    /**
     * Shared failure path for both typed pipeline exceptions and unexpected
     * ones. The document is already moved to `failed` by the pipeline's own
     * catch block by the time this runs — what remains is deciding whether
     * Laravel gets to retry, and if so, putting the document back in a state
     * where the next attempt's `begin()` transition is legal rather than
     * fighting `failed -> validating`.
     */
    private function handleFailure(
        Throwable $e,
        bool $retryable,
        string $operatorMessage,
        KnowledgeDocument $document,
        ?KnowledgeProcessingJob $job,
        ProcessingStateMachine $states,
    ): void {
        $willRetry = $retryable && $this->attempts() < $this->tries;

        $job?->forceFill([
            'status' => $willRetry ? ProcessingJobStatus::Retrying->value : ProcessingJobStatus::Failed->value,
            'last_error' => $operatorMessage,
            'finished_at' => $willRetry ? null : now(),
        ])->save();

        if ($willRetry) {
            // The pipeline already recorded the failure and moved the
            // document to `failed`. Move it back to `queued` so the next
            // attempt's transition to `validating` is legal — Laravel is
            // about to retry this job.
            $states->requeueForRetry($document);

            throw $e;
        }

        if (! $retryable) {
            // A permanent failure is already recorded against the document by
            // the pipeline; re-throwing would only burn a retry on work that
            // cannot succeed.
            return;
        }

        // Retryable, but attempts are exhausted: rethrow so Laravel's own
        // tries accounting marks this queue job failed and engages failed(),
        // queue:failed, and queue:retry. The document already sits in
        // `failed` from the pipeline's catch block.
        throw $e;
    }

    public function failed(Throwable $exception): void
    {
        if (! $this->processingJobId) {
            return;
        }

        // failed() is NOT wrapped by the job's middleware() stack, so tenancy
        // is not guaranteed to be initialized here — and in a long-running
        // worker process, the *previous* job's tenant connection could still
        // be active. Re-establishing tenancy explicitly is what stops tenant
        // A's failure handling from writing into tenant B's database.
        $this->runInTenantContext(function () use ($exception): void {
            KnowledgeProcessingJob::query()->whereKey($this->processingJobId)->update([
                'status' => ProcessingJobStatus::Failed->value,
                'finished_at' => now(),
                'last_error' => 'Processing failed after '.$this->tries.' attempts: '.$exception->getMessage(),
            ]);

            $document = KnowledgeDocument::find($this->documentId);

            // Belt-and-suspenders: the pipeline's own catch block already
            // fails the document on every attempt, so this only matters if
            // something threw before the pipeline could record it at all.
            if ($document && $document->status->isInFlight()) {
                app(ProcessingStateMachine::class)->fail(
                    $document,
                    $document->current_stage ?? ProcessingStage::Extracting,
                    FailureCategory::Unknown,
                    'Processing failed after the maximum number of attempts.',
                );
            }
        });
    }
}
