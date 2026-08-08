<?php

namespace App\Jobs\Knowledge;

use App\Enums\Knowledge\ProcessingJobStatus;
use App\Enums\Knowledge\ProcessingStage;
use App\Exceptions\Knowledge\KnowledgeException;
use App\Jobs\Concerns\TenantAware;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Services\Knowledge\DocumentProcessingService;
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

    public function handle(DocumentProcessingService $processor): void
    {
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

            $job?->forceFill([
                'status' => ProcessingJobStatus::Completed->value,
                'current_stage' => ProcessingStage::Ready->value,
                'progress' => 100,
                'items_processed' => $result['chunks'],
                'finished_at' => now(),
                'duration_ms' => $job->started_at ? (int) (now()->getPreciseTimestamp(3) - $job->started_at->getPreciseTimestamp(3)) : null,
            ])->save();

            // Chunks needing a vector are embedded by a separate job so that a
            // rate limit retries only the embedding, not the extraction.
            if ($result['status'] === 'awaiting_embedding') {
                EmbedKnowledgeBaseJob::dispatch($document->knowledge_base_id);
            }
        } catch (KnowledgeException $e) {
            $job?->forceFill([
                'status' => $e->isRetryable() && $this->attempts() < $this->tries
                    ? ProcessingJobStatus::Retrying->value
                    : ProcessingJobStatus::Failed->value,
                'last_error' => $e->operatorMessage(),
                'finished_at' => now(),
            ])->save();

            // A permanent failure is already recorded against the document by
            // the pipeline; re-throwing would only burn the remaining attempts
            // on work that cannot succeed.
            if (! $e->isRetryable()) {
                return;
            }

            throw $e;
        } catch (Throwable $e) {
            $job?->forceFill([
                'status' => ProcessingJobStatus::Failed->value,
                'last_error' => 'Processing failed unexpectedly.',
                'finished_at' => now(),
            ])->save();

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        if (! $this->processingJobId) {
            return;
        }

        KnowledgeProcessingJob::query()->whereKey($this->processingJobId)->update([
            'status' => ProcessingJobStatus::Failed->value,
            'finished_at' => now(),
            'last_error' => 'Processing failed after '.$this->tries.' attempts.',
        ]);
    }
}
