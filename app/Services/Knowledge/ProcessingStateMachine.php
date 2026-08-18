<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\ProcessingStage;
use App\Models\Knowledge\KnowledgeDocument;
use LogicException;

/**
 * The only sanctioned way to change a document's processing status.
 *
 * Statuses are not free-form strings: a queue worker that races another and
 * writes `ready` over `failed`, or resets an indexed document to `uploaded`,
 * produces knowledge that the UI claims is live and retrieval cannot see.
 * Transitions are validated against DocumentStatus::allowedTransitions() and the
 * write is conditional on the row still holding the status we read, so the
 * loser of a race is rejected rather than silently overwriting.
 */
class ProcessingStateMachine
{
    public function __construct(private readonly ProcessingLogger $logger) {}

    /**
     * Attempts a transition. Returns false if another worker already moved the
     * row — callers treat that as "someone else owns this now", not an error.
     *
     * @param  array<string, mixed>  $attributes  Extra columns to write atomically with the status.
     */
    public function transition(
        KnowledgeDocument $document,
        DocumentStatus $to,
        ?ProcessingStage $stage = null,
        array $attributes = [],
    ): bool {
        $from = $document->status;

        if ($from === $to && $stage === null && $attributes === []) {
            return true;
        }

        // A same-status call is never an illegal "transition" — it is a stage
        // or attribute refresh within the resting status the document already
        // holds (e.g. Processing -> Processing to advance from "normalizing"
        // to "detecting_language"). Only a genuine status change is checked
        // against the allowed-transition table.
        if ($from !== $to && ! $from->canTransitionTo($to)) {
            throw new LogicException(
                "Illegal knowledge document transition {$from->value} -> {$to->value} (document #{$document->id})."
            );
        }

        $payload = array_merge($attributes, [
            'status' => $to->value,
            'current_stage' => $stage?->value ?? $document->current_stage?->value,
            'updated_at' => now(),
        ]);

        // Conditional update: only succeeds while the row still holds the status
        // this call was reasoned about.
        $updated = KnowledgeDocument::query()
            ->whereKey($document->id)
            ->where('status', $from->value)
            ->update($payload);

        if ($updated === 0) {
            return false;
        }

        $document->forceFill($payload);
        $document->syncOriginal();

        return true;
    }

    /** Marks the start of a processing attempt, clearing the previous failure. */
    public function begin(KnowledgeDocument $document): bool
    {
        return $this->transition($document, DocumentStatus::Validating, ProcessingStage::Validating, [
            'processing_started_at' => now(),
            'processing_completed_at' => null,
            'processing_duration_ms' => null,
            'last_error' => null,
            'failure_stage' => null,
            'failure_category' => null,
        ]);
    }

    public function queue(KnowledgeDocument $document): bool
    {
        // Same-status call: transition() now treats this as a refresh rather
        // than an illegal self-transition, so re-queuing a document that is
        // already queued (e.g. a duplicate upload while a worker has not yet
        // picked it up) is a harmless no-op instead of a crash.
        return $this->transition($document, DocumentStatus::Queued, ProcessingStage::Uploaded);
    }

    /**
     * Completes processing. Requires that every chunk has already been
     * resolved (embedded or permanently failed) — callers must not call this
     * while chunks are still pending, or a genuinely unfinished document will
     * be reported as done.
     *
     *   chunksEmbedded == chunksTotal (> 0)  -> ready
     *   0 < chunksEmbedded < chunksTotal     -> partially_ready
     *   chunksEmbedded == 0                  -> failed (nothing survived embedding)
     *   chunksTotal == 0                     -> failed (nothing to search)
     */
    public function complete(KnowledgeDocument $document, int $chunksTotal, int $chunksEmbedded): bool
    {
        if ($chunksTotal === 0 || $chunksEmbedded === 0) {
            return $this->fail(
                $document,
                ProcessingStage::Embedding,
                FailureCategory::EmbeddingProviderError,
                $chunksTotal === 0
                    ? 'No chunks were produced from this document, so there is nothing to search.'
                    : 'All chunks failed to generate embeddings.',
            );
        }

        $status = $chunksEmbedded < $chunksTotal
            ? DocumentStatus::PartiallyReady
            : DocumentStatus::Ready;

        $duration = $document->processing_started_at
            ? (int) (now()->getPreciseTimestamp(3) - $document->processing_started_at->getPreciseTimestamp(3))
            : null;

        return $this->transition($document, $status, ProcessingStage::Ready, [
            'processing_completed_at' => now(),
            'processing_duration_ms' => $duration,
            'indexed_at' => now(),
            'chunk_count' => $chunksTotal,
            'retry_count' => 0,
        ]);
    }

    public function fail(
        KnowledgeDocument $document,
        ProcessingStage $stage,
        FailureCategory $category,
        string $operatorMessage,
    ): bool {
        return $this->transition($document, DocumentStatus::Failed, $stage, [
            'last_error' => $operatorMessage,
            'failure_stage' => $stage->value,
            'failure_category' => $category->value,
            'processing_completed_at' => now(),
            'retry_count' => $document->retry_count + 1,
        ]);
    }

    /**
     * Puts a failed or archived document back at the head of the pipeline.
     * Chunks are left in place until the new run supersedes them, so a retry
     * does not blank out knowledge that currently works.
     */
    public function requeueForRetry(KnowledgeDocument $document): bool
    {
        if (! $document->status->canTransitionTo(DocumentStatus::Queued)) {
            return false;
        }

        return $this->transition($document, DocumentStatus::Queued, ProcessingStage::Uploaded, [
            'last_error' => null,
            'failure_stage' => null,
            'failure_category' => null,
        ]);
    }

    /** Advances the in-flight stage without changing the resting status. */
    public function advance(KnowledgeDocument $document, DocumentStatus $to, ProcessingStage $stage): bool
    {
        $moved = $this->transition($document, $to, $stage);

        if ($moved) {
            $this->logger->stage($document, $stage, $stage->label());
        }

        return $moved;
    }

    /**
     * Ends in-flight work as cancelled rather than failed.
     *
     * A document that never had working content lands in `cancelled` — there
     * is nothing to fall back to. A document that already had ready chunks
     * and was mid-reindex lands in `outdated` instead: its existing chunks are
     * never touched by cancellation (only an explicit withdraw call clears
     * `is_retrievable`), so they keep answering queries until a future
     * successful reindex replaces them.
     */
    public function cancel(KnowledgeDocument $document): bool
    {
        $hadWorkingContent = $document->indexed_at !== null || $document->chunk_count > 0;
        $target = $hadWorkingContent ? DocumentStatus::Outdated : DocumentStatus::Cancelled;

        if ($document->status !== $target && ! $document->status->canTransitionTo($target)) {
            return false;
        }

        return $this->transition($document, $target, $document->current_stage, [
            'last_error' => $hadWorkingContent
                ? 'Reindex cancelled by operator; previous content remains available.'
                : 'Cancelled by operator before processing completed.',
            'processing_completed_at' => now(),
        ]);
    }
}
