<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\ProcessingJobStatus;
use App\Enums\Knowledge\ProcessingStage;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeProcessingJob;

/**
 * Recovers work orphaned by a worker that died without reporting back.
 *
 * Closing out the stale KnowledgeProcessingJob row alone is not enough: the
 * document it was working on can be left sitting in an in-flight status
 * (validating, extracting, embedding, ...) forever, which makes every future
 * retry/reindex attempt refuse to touch it — the state machine sees it as
 * still owned by whatever worker vanished. This reconciles both halves
 * together so operators always end up with a document that is either
 * retryable or cleanly failed, never orphaned.
 *
 * Intended to be called once per tenant from inside an already-initialized
 * tenancy() context (see ReleaseStaleKnowledgeJobsCommand) — it never touches
 * tenancy itself, so it is safe to reuse from any tenant-scoped caller.
 */
class StaleProcessingRecoveryService
{
    public function __construct(private readonly ProcessingStateMachine $states) {}

    /** @return int number of stale jobs released */
    public function releaseStaleJobs(): int
    {
        $staleJobs = KnowledgeProcessingJob::query()->stale()->get();

        foreach ($staleJobs as $job) {
            $job->forceFill([
                'status' => ProcessingJobStatus::Failed->value,
                'finished_at' => now(),
                'last_error' => 'The worker processing this job stopped responding. Retry the source.',
            ])->save();

            $this->reconcileDocument($job);
        }

        return $staleJobs->count();
    }

    private function reconcileDocument(KnowledgeProcessingJob $job): void
    {
        if (! $job->knowledge_document_id) {
            return;
        }

        $document = KnowledgeDocument::find($job->knowledge_document_id);

        if (! $document || ! $document->status->isInFlight()) {
            return;
        }

        $this->states->fail(
            $document,
            $document->current_stage ?? ProcessingStage::Extracting,
            FailureCategory::Unknown,
            'Processing stopped unexpectedly — the worker did not report back. Retry to resume.',
        );
    }
}
