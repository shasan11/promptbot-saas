<?php

namespace App\Jobs\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Jobs\Concerns\TenantAware;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeFaq;
use App\Services\Knowledge\KnowledgeIndexService;
use App\Services\Knowledge\ProcessingStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Rebuilds a knowledge base's index.
 *
 * Two modes:
 *   $rechunk = false  re-embed existing chunks (embedding model changed)
 *   $rechunk = true   re-extract and re-chunk every document (chunking settings
 *                     changed, or a bad extraction needs redoing)
 *
 * Re-embedding is the cheaper path and is what an embedding-model change needs;
 * re-chunking pays the full extraction cost again, so it is opt-in.
 */
class ReindexKnowledgeBaseJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 2;

    public int $timeout = 1800;

    public int $uniqueFor = 3600;

    public function __construct(
        private readonly int $knowledgeBaseId,
        private readonly bool $rechunk = false,
    ) {
        $this->captureTenant();
        $this->onQueue(config('knowledge.queues.processing'));
    }

    public function uniqueId(): string
    {
        return ($this->tenantId ?? 'central').':reindex:'.$this->knowledgeBaseId;
    }

    public function handle(KnowledgeIndexService $index, ProcessingStateMachine $states): void
    {
        $base = KnowledgeBase::find($this->knowledgeBaseId);

        if (! $base) {
            return;
        }

        if ($this->rechunk) {
            KnowledgeDocument::query()
                ->where('knowledge_base_id', $base->id)
                ->whereIn('status', [
                    DocumentStatus::Ready->value, DocumentStatus::PartiallyReady->value,
                    DocumentStatus::Failed->value, DocumentStatus::Outdated->value,
                ])
                ->orderBy('id')
                ->chunkById(100, function ($documents) use ($states): void {
                    foreach ($documents as $document) {
                        if ($states->requeueForRetry($document)) {
                            // force: true bypasses the unchanged-content
                            // short-circuit, which is the whole point of an
                            // explicit re-chunk.
                            ProcessKnowledgeDocumentJob::dispatch($document->id, null, true);
                        }
                    }
                });

            return;
        }

        // Clears every vector and marks the chunks pending; EmbedKnowledgeBaseJob
        // rebuilds them in provider-sized batches.
        $index->markBaseForReindex($base->id);

        KnowledgeFaq::query()
            ->where('knowledge_base_id', $base->id)
            ->published()
            ->update(['indexed_at' => null]);

        EmbedKnowledgeBaseJob::dispatch($base->id);
    }
}
