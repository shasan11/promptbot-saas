<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\ProcessingJobStatus;
use App\Enums\Knowledge\ProcessingStage;
use App\Exceptions\Knowledge\KnowledgeException;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeFailure;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeProcessingLog;
use App\Models\Knowledge\KnowledgeSource;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records what the pipeline did, in three places for three audiences:
 *
 *   knowledge_processing_logs — the per-stage trail an operator reads on a
 *                               document's "Processing logs" tab
 *   knowledge_failures        — the actionable failure the Failed Sources page
 *                               lists, with technical detail gated separately
 *   the application log       — structured lines for whatever aggregates them,
 *                               always carrying tenant/base/source/correlation
 *
 * Content is never logged. A processing log is readable by anyone with
 * knowledge.sources.view, and document bodies routinely contain exactly the
 * confidential material the permission model exists to protect.
 */
class ProcessingLogger
{
    private ?KnowledgeProcessingJob $job = null;

    private ?string $correlationId = null;

    public function forJob(?KnowledgeProcessingJob $job): self
    {
        $clone = clone $this;
        $clone->job = $job;
        $clone->correlationId = $job?->correlation_id;

        return $clone;
    }

    public function withCorrelationId(string $correlationId): self
    {
        $clone = clone $this;
        $clone->correlationId = $correlationId;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  int|null  $knowledgeBaseId  Fallback attribution when no single
     *                                     document owns this log line — e.g. an
     *                                     embedding batch spanning several
     *                                     documents. Ignored when $document is set.
     */
    public function stage(
        ?KnowledgeDocument $document,
        ProcessingStage $stage,
        string $message,
        array $context = [],
        ?int $durationMs = null,
        ?int $knowledgeBaseId = null,
    ): void {
        KnowledgeProcessingLog::create([
            'knowledge_processing_job_id' => $this->job?->id,
            'knowledge_document_id' => $document?->id,
            'knowledge_source_id' => $document?->knowledge_source_id,
            'stage' => $stage->value,
            'level' => 'info',
            'message' => mb_substr($message, 0, 1000),
            'context' => $this->sanitise($context) ?: null,
            'duration_ms' => $durationMs,
        ]);

        Log::info('knowledge.stage', $this->structured([
            'stage' => $stage->value,
            'document_id' => $document?->id,
            'source_id' => $document?->knowledge_source_id,
            'knowledge_base_id' => $document?->knowledge_base_id ?? $knowledgeBaseId,
            'duration_ms' => $durationMs,
        ] + $this->sanitise($context)));

        $this->advanceJobStage($stage);
    }

    /**
     * Mirrors a pipeline stage onto the operator-visible KnowledgeProcessingJob
     * row, so the Processing screen shows live progress instead of a row that
     * only changes at the very end. Guarded to `running` jobs so a race with
     * completion/failure/cancellation never resurrects a finished row.
     */
    private function advanceJobStage(ProcessingStage $stage): void
    {
        if (! $this->job) {
            return;
        }

        KnowledgeProcessingJob::query()
            ->whereKey($this->job->id)
            ->where('status', ProcessingJobStatus::Running->value)
            ->update([
                'current_stage' => $stage->value,
                'progress' => $stage->progress(),
                'updated_at' => now(),
            ]);
    }

    /** @param  array<string, mixed>  $context */
    public function warn(?KnowledgeDocument $document, ProcessingStage $stage, string $message, array $context = []): void
    {
        KnowledgeProcessingLog::create([
            'knowledge_processing_job_id' => $this->job?->id,
            'knowledge_document_id' => $document?->id,
            'knowledge_source_id' => $document?->knowledge_source_id,
            'stage' => $stage->value,
            'level' => 'warning',
            'message' => mb_substr($message, 0, 1000),
            'context' => $this->sanitise($context) ?: null,
        ]);

        Log::warning('knowledge.stage', $this->structured([
            'stage' => $stage->value,
            'document_id' => $document?->id,
            'message' => $message,
        ] + $this->sanitise($context)));
    }

    /**
     * Records a failure and returns the row, so callers can attach its uuid to
     * whatever they surface to the user.
     *
     * @param  int|null  $knowledgeBaseId  Fallback attribution used only when
     *                                     neither $document nor $source is
     *                                     available — e.g. an embedding batch
     *                                     failure that could not be traced back
     *                                     to a single document. Without this,
     *                                     the failure row's knowledge_base_id
     *                                     is left null and disappears from that
     *                                     base's Failed Sources page.
     */
    public function failure(
        ProcessingStage $stage,
        Throwable $exception,
        ?KnowledgeDocument $document = null,
        ?KnowledgeSource $source = null,
        int $attempt = 1,
        ?int $knowledgeBaseId = null,
    ): KnowledgeFailure {
        $category = $exception instanceof KnowledgeException
            ? $exception->category()
            : FailureCategory::Unknown;

        $operatorMessage = $exception instanceof KnowledgeException
            ? $exception->operatorMessage()
            : $category->remediation();

        $source ??= $document?->source;

        $failure = KnowledgeFailure::create([
            'knowledge_base_id' => $document?->knowledge_base_id ?? $source?->knowledge_base_id ?? $knowledgeBaseId,
            'knowledge_source_id' => $source?->id,
            'knowledge_document_id' => $document?->id,
            'knowledge_processing_job_id' => $this->job?->id,
            'stage' => $stage->value,
            'category' => $category->value,
            'message' => $operatorMessage,
            // Kept out of `message` deliberately — this is the only field that
            // may quote internals, and the model hides it from serialisation.
            'technical_details' => $this->technicalDetails($exception),
            'attempt' => $attempt,
            'retryable' => $category->isTransient(),
        ]);

        KnowledgeProcessingLog::create([
            'knowledge_processing_job_id' => $this->job?->id,
            'knowledge_document_id' => $document?->id,
            'knowledge_source_id' => $source?->id,
            'stage' => $stage->value,
            'level' => 'error',
            'message' => mb_substr($operatorMessage, 0, 1000),
            'context' => ['category' => $category->value, 'attempt' => $attempt, 'failure_uuid' => $failure->uuid],
        ]);

        Log::error('knowledge.failure', $this->structured([
            'stage' => $stage->value,
            'category' => $category->value,
            'attempt' => $attempt,
            'document_id' => $document?->id,
            'source_id' => $source?->id,
            'knowledge_base_id' => $failure->knowledge_base_id,
            'failure_uuid' => $failure->uuid,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]));

        return $failure;
    }

    /** @param  array<string, mixed>  $payload  @return array<string, mixed> */
    private function structured(array $payload): array
    {
        return array_filter([
            'tenant_id' => tenancy()->initialized ? tenant('id') : null,
            'correlation_id' => $this->correlationId,
            'job_id' => $this->job?->id,
        ] + $payload, fn ($value) => $value !== null);
    }

    private function technicalDetails(Throwable $exception): string
    {
        $lines = [
            $exception::class.': '.$exception->getMessage(),
            $exception->getFile().':'.$exception->getLine(),
        ];

        if ($previous = $exception->getPrevious()) {
            $lines[] = 'Caused by '.$previous::class.': '.$previous->getMessage();
        }

        // Bounded: a full trace on a deeply nested queue stack can run to tens
        // of kilobytes per failure row.
        $lines[] = mb_substr($exception->getTraceAsString(), 0, 4000);

        return implode("\n", $lines);
    }

    /**
     * Strips anything that could carry document text or secrets out of a log
     * context. Only scalars survive, and long strings are truncated — a caller
     * passing a chunk body by mistake must not leak it into a readable log.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitise(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            if (preg_match('/content|text|body|secret|token|password|key|answer|question/i', (string) $key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safe[$key] = is_string($value) ? mb_substr($value, 0, 200) : $value;
            }
        }

        return $safe;
    }
}
