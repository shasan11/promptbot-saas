<?php

namespace App\Jobs\Knowledge;

use App\Jobs\Concerns\TenantAware;
use App\Models\Knowledge\KnowledgeBase;
use App\Services\Knowledge\EmbeddingService;
use App\Services\Knowledge\KnowledgeStatisticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Embeds a knowledge base's pending chunks.
 *
 * Unique per base (ShouldBeUnique): uploading twenty files at once would
 * otherwise queue twenty identical jobs that all compete for the same pending
 * rows and the same provider rate limit. One job drains the backlog, and
 * re-dispatches itself if more work arrived while it ran.
 */
class EmbedKnowledgeBaseJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 5;

    public int $timeout = 900;

    /** How long the uniqueness lock survives if the worker dies mid-run. */
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

    public function backoff(): array
    {
        return [30, 120, 300, 900, 1800];
    }

    public function handle(EmbeddingService $embeddings, KnowledgeStatisticsService $statistics): void
    {
        $base = KnowledgeBase::find($this->knowledgeBaseId);

        if (! $base) {
            return;
        }

        $result = $embeddings->embedPending($base, limit: 2000);

        $statistics->refreshForBase($base);

        if ($result['embedded'] > 0) {
            $base->forceFill(['last_indexed_at' => now()])->save();
        }

        // Anything still pending means the batch limit was hit, not that
        // embedding failed. Re-dispatch so the backlog drains rather than
        // stalling until the next unrelated upload.
        if ($embeddings->pendingCount($base) > 0) {
            self::dispatch($this->knowledgeBaseId)->delay(now()->addSeconds(5));
        }

        $this->markDocumentsReady($base->id);
    }

    /**
     * Promotes documents whose chunks have all been embedded.
     *
     * Done here rather than in the pipeline because embedding is what a document
     * is waiting on — the pipeline has already finished its part by this point.
     */
    private function markDocumentsReady(int $knowledgeBaseId): void
    {
        $documents = \App\Models\Knowledge\KnowledgeDocument::query()
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->where('status', \App\Enums\Knowledge\DocumentStatus::Embedding->value)
            ->get(['id', 'uuid', 'status', 'chunk_count', 'processing_started_at', 'current_stage']);

        $states = app(\App\Services\Knowledge\ProcessingStateMachine::class);

        foreach ($documents as $document) {
            $counts = \App\Models\Knowledge\KnowledgeChunk::query()
                ->where('owner_key', 'document:'.$document->id)
                ->selectRaw(
                    'count(*) as total,'
                    .' sum(case when embedding_status = ? then 1 else 0 end) as ready,'
                    .' sum(case when embedding_status = ? then 1 else 0 end) as pending',
                    [\App\Models\Knowledge\KnowledgeChunk::EMBEDDING_READY, \App\Models\Knowledge\KnowledgeChunk::EMBEDDING_PENDING]
                )
                ->first();

            // Still work outstanding — leave it in `embedding`.
            if ((int) $counts->pending > 0) {
                continue;
            }

            $states->transition($document, \App\Enums\Knowledge\DocumentStatus::Indexing, \App\Enums\Knowledge\ProcessingStage::Indexing);
            $states->complete($document, (int) $counts->total, (int) $counts->ready);

            \App\Events\Knowledge\KnowledgeProcessingCompleted::dispatch($document->id, (int) $counts->total);
        }
    }
}
