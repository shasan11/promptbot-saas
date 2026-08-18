<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\ProcessingJobStatus;
use App\Enums\Knowledge\ProcessingStage;
use App\Models\Knowledge\KnowledgeChunk;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeProcessingJob;

/**
 * Keeps the operator-visible KnowledgeProcessingJob row honest about the
 * *entire* ingestion lifecycle, not just whichever queue job last touched it.
 *
 * Extraction/chunking and embedding run as two separate queue jobs so a rate
 * limit retries only the embedding half — but the job row a human watches on
 * the Processing screen must still read "completed" only once both halves are
 * actually done. This class is the seam between the two jobs.
 */
class ProcessingJobTracker
{
    /** The document's own extraction/chunking pass finished, but chunks are still awaiting a vector. */
    public function markAwaitingEmbedding(KnowledgeProcessingJob $job, int $chunksWritten): void
    {
        $job->forceFill([
            'status' => ProcessingJobStatus::Running->value,
            'current_stage' => ProcessingStage::Embedding->value,
            'progress' => ProcessingStage::Embedding->progress(),
            'items_total' => $chunksWritten,
            'finished_at' => null,
        ])->save();
    }

    /** The document reached a terminal ready state without ever needing async embedding. */
    public function markCompleted(KnowledgeProcessingJob $job, int $chunksWritten): void
    {
        $job->forceFill([
            'status' => ProcessingJobStatus::Completed->value,
            'current_stage' => ProcessingStage::Ready->value,
            'progress' => 100,
            'items_total' => $chunksWritten,
            'items_processed' => $chunksWritten,
            'finished_at' => now(),
            'duration_ms' => $this->durationMs($job),
        ])->save();
    }

    public function markCancelled(KnowledgeProcessingJob $job, string $message): void
    {
        $job->forceFill([
            'status' => ProcessingJobStatus::Cancelled->value,
            'last_error' => $message,
            'finished_at' => now(),
            'duration_ms' => $this->durationMs($job),
        ])->save();
    }

    /**
     * Another attempt/worker already owns this document. Not a failure — the
     * job simply stands down, but "completed" would be a lie and leaving it
     * "running" forever would eventually trip stale-job recovery for no reason.
     */
    public function markStoodDown(KnowledgeProcessingJob $job): void
    {
        $job->forceFill([
            'status' => ProcessingJobStatus::Cancelled->value,
            'last_error' => 'Skipped — this document was already owned by another processing attempt.',
            'finished_at' => now(),
            'duration_ms' => $this->durationMs($job),
        ])->save();
    }

    /**
     * Finalizes the KnowledgeProcessingJob tied to one document once its
     * embedding (and therefore its whole ingestion) is genuinely settled.
     *
     * Looked up per document and updated one row at a time: EmbedKnowledgeBaseJob
     * processes chunks belonging to many documents in a single pass, and each
     * document's own job record must be finalized independently so completing
     * one document's ingestion never marks another document's job done.
     */
    public function finalizeForDocument(KnowledgeDocument $document): void
    {
        $job = KnowledgeProcessingJob::query()
            ->where('knowledge_document_id', $document->id)
            ->where('job_type', KnowledgeProcessingJob::TYPE_DOCUMENT)
            ->whereIn('status', [ProcessingJobStatus::Running->value, ProcessingJobStatus::Retrying->value])
            ->latest('id')
            ->first();

        if (! $job) {
            return;
        }

        $counts = KnowledgeChunk::query()
            ->where('owner_key', $document->chunkOwnerKey())
            ->selectRaw(
                'count(*) as total,'
                .' sum(case when embedding_status = ? then 1 else 0 end) as ready,'
                .' sum(case when embedding_status = ? then 1 else 0 end) as failed',
                [KnowledgeChunk::EMBEDDING_READY, KnowledgeChunk::EMBEDDING_FAILED]
            )
            ->first();

        $isFailure = $document->status === DocumentStatus::Failed;

        $job->forceFill([
            'status' => $isFailure ? ProcessingJobStatus::Failed->value : ProcessingJobStatus::Completed->value,
            'current_stage' => $document->current_stage?->value ?? ProcessingStage::Ready->value,
            'progress' => $isFailure ? $job->progress : 100,
            'items_total' => (int) ($counts->total ?? $job->items_total ?? 0),
            'items_processed' => (int) ($counts->ready ?? 0),
            'items_failed' => (int) ($counts->failed ?? 0),
            'last_error' => $isFailure ? ($document->last_error ?? $job->last_error) : $job->last_error,
            'finished_at' => now(),
            'duration_ms' => $this->durationMs($job),
        ])->save();
    }

    private function durationMs(KnowledgeProcessingJob $job): ?int
    {
        return $job->started_at
            ? (int) (now()->getPreciseTimestamp(3) - $job->started_at->getPreciseTimestamp(3))
            : null;
    }
}
