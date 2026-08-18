<?php

namespace App\Enums\Knowledge;

/**
 * Lifecycle of a single ingested knowledge item (uploaded document, crawled
 * page body, FAQ, manual text). Transitions are enforced centrally by
 * App\Services\Knowledge\ProcessingStateMachine — never mutate `status`
 * directly on the model.
 */
enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Queued = 'queued';
    case Validating = 'validating';
    case Extracting = 'extracting';
    case Processing = 'processing';
    case Chunking = 'chunking';
    case Embedding = 'embedding';
    case Indexing = 'indexing';
    case Ready = 'ready';
    case PartiallyReady = 'partially_ready';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Outdated = 'outdated';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Queued => 'Queued',
            self::Validating => 'Validating',
            self::Extracting => 'Extracting text',
            self::Processing => 'Processing',
            self::Chunking => 'Chunking',
            self::Embedding => 'Generating embeddings',
            self::Indexing => 'Indexing',
            self::Ready => 'Ready',
            self::PartiallyReady => 'Partially ready',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Outdated => 'Outdated',
            self::Archived => 'Archived',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Ready, self::PartiallyReady, self::Failed, self::Cancelled, self::Outdated, self::Archived], true);
    }

    public function isInFlight(): bool
    {
        return in_array($this, [
            self::Queued, self::Validating, self::Extracting, self::Processing,
            self::Chunking, self::Embedding, self::Indexing,
        ], true);
    }

    /** Only ready/partially-ready content participates in retrieval. */
    public function isRetrievable(): bool
    {
        return in_array($this, [self::Ready, self::PartiallyReady], true);
    }

    /**
     * Rough completion percentage for the processing UI. Deliberately coarse —
     * fine-grained progress comes from `ProcessingStage` counters on the job row.
     */
    public function progress(): int
    {
        return match ($this) {
            self::Uploaded => 5,
            self::Queued => 10,
            self::Validating => 20,
            self::Extracting => 35,
            self::Processing => 45,
            self::Chunking => 60,
            self::Embedding => 80,
            self::Indexing => 92,
            self::Ready, self::PartiallyReady, self::Outdated, self::Archived => 100,
            self::Failed, self::Cancelled => 0,
        };
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Uploaded => [self::Queued, self::Validating, self::Failed, self::Cancelled, self::Archived],
            self::Queued => [self::Validating, self::Failed, self::Cancelled, self::Outdated, self::Archived],
            self::Validating => [self::Extracting, self::Failed, self::Cancelled, self::Outdated],
            self::Extracting => [self::Processing, self::Chunking, self::Failed, self::Cancelled, self::Outdated],
            self::Processing => [self::Chunking, self::Failed, self::Cancelled, self::Outdated],
            self::Chunking => [self::Embedding, self::Failed, self::Cancelled, self::Outdated],
            self::Embedding => [self::Indexing, self::PartiallyReady, self::Failed, self::Cancelled, self::Outdated],
            self::Indexing => [self::Ready, self::PartiallyReady, self::Failed, self::Cancelled, self::Outdated],
            self::Ready => [self::Outdated, self::Archived, self::Queued],
            self::PartiallyReady => [self::Ready, self::Outdated, self::Archived, self::Queued],
            // A failed or cancelled item can only re-enter the pipeline from the top.
            self::Failed => [self::Queued, self::Archived],
            self::Cancelled => [self::Queued, self::Archived],
            self::Outdated => [self::Queued, self::Archived, self::Ready],
            self::Archived => [self::Queued],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
