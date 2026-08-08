<?php

namespace App\Jobs\Knowledge;

use App\Jobs\Concerns\TenantAware;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeChunk;
use App\Models\Knowledge\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Permanent deletion: removes stored files, chunks and rows for good.
 *
 * Runs asynchronously because a large base means thousands of object-storage
 * deletes, which must not happen inside the request that clicked the button.
 * The soft delete has already taken the content out of retrieval by the time
 * this runs, so there is no window where deleted knowledge still answers.
 */
class PurgeKnowledgeResourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(
        private readonly string $resourceType,
        private readonly int $resourceId,
    ) {
        $this->captureTenant();
        $this->onQueue(config('knowledge.queues.low'));
    }

    public function handle(KnowledgeStorage $storage): void
    {
        match ($this->resourceType) {
            'knowledge_base' => $this->purgeBase($storage),
            'document' => $this->purgeDocument($storage),
            default => null,
        };
    }

    private function purgeBase(KnowledgeStorage $storage): void
    {
        $base = KnowledgeBase::withTrashed()->find($this->resourceId);

        if (! $base) {
            return;
        }

        // Files first: a crash midway leaves orphaned rows, which are
        // discoverable and re-purgeable. The reverse leaves orphaned *files*,
        // which nothing references and nothing will ever clean up.
        $storage->deleteBaseDirectory($base->uuid);

        DB::transaction(function () use ($base): void {
            // Chunks are deleted explicitly rather than left to the FK cascade
            // so the delete is batched — a cascade over 100k rows inside one
            // statement is a long lock on the busiest table in the schema.
            KnowledgeChunk::query()->where('knowledge_base_id', $base->id)->delete();
            $base->forceDelete();
        });
    }

    private function purgeDocument(KnowledgeStorage $storage): void
    {
        $document = KnowledgeDocument::withTrashed()->with('versions')->find($this->resourceId);

        if (! $document) {
            return;
        }

        $paths = $document->versions
            ->pluck('storage_path')
            ->push($document->storage_path)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $storage->deletePaths($paths, $document->storage_disk);

        DB::transaction(function () use ($document): void {
            KnowledgeChunk::query()->where('owner_key', $document->chunkOwnerKey())->delete();
            $document->forceDelete();
        });
    }
}
