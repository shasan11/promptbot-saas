<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\SourceType;
use App\Exceptions\Knowledge\KnowledgeException;
use App\Models\Knowledge\KnowledgeChunk;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeFaq;
use App\Models\Knowledge\KnowledgeSource;
use App\Services\Knowledge\Data\ChunkCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persists chunks and keeps the index consistent with its sources.
 *
 * The central guarantee here is idempotency. A queue can run the same job twice
 * — a worker dies after writing chunks but before acking, a user clicks
 * "re-index" during a run — and the result must be the same index either way,
 * not a doubled one.
 *
 * The mechanism: chunks are upserted on (owner_key, chunk_index), a vector is
 * reused when the content hash and embedding signature both still match, and
 * any trailing rows from a longer previous version are deleted. So re-running an
 * unchanged document costs one query per batch and zero embedding calls.
 */
class KnowledgeIndexService
{
    public function __construct(private readonly KnowledgeStatisticsService $statistics) {}

    /**
     * Replaces a document's chunks with `$candidates`.
     *
     * @param  array<int, ChunkCandidate>  $candidates
     * @return array{written: int, reused: int, removed: int}
     */
    public function syncDocumentChunks(KnowledgeDocument $document, array $candidates): array
    {
        $source = $document->source;
        $base = $document->knowledgeBase;

        // The source relation is null once its row is soft-deleted. A
        // document can still be mid-retry when that happens (its own queue
        // job was already in flight when the source was removed), and must
        // fail cleanly here rather than crash on a null property access.
        if (! $source) {
            throw new KnowledgeException(
                "Chunking failed for document #{$document->id}: its source (#{$document->knowledge_source_id}) no longer exists.",
                FailureCategory::InvalidFile,
                'The source this document belonged to was deleted. Remove this document — it can no longer be processed.',
            );
        }

        $metadata = [
            'document_name' => $document->title,
            'source_type' => $source->source_type->value,
            'collection' => $document->collection?->name,
            'last_updated' => $document->updated_at?->toDateString(),
        ];

        if ($document->kind === KnowledgeDocument::KIND_WEBSITE_PAGE) {
            $metadata['url'] = $document->websitePage?->canonical_url ?? $document->websitePage?->url;
        }

        return $this->sync(
            ownerKey: $document->chunkOwnerKey(),
            candidates: $candidates,
            columns: [
                'knowledge_base_id' => $document->knowledge_base_id,
                'knowledge_source_id' => $document->knowledge_source_id,
                'knowledge_collection_id' => $document->knowledge_collection_id,
                'knowledge_document_id' => $document->id,
                'knowledge_website_page_id' => $document->websitePage?->id,
                'knowledge_faq_id' => null,
                'source_type' => $source->source_type->value,
                'priority' => $source->priority->value,
                'language' => $document->language ?? $base->default_language,
                'effective_from' => $document->effective_from ?? $source->effective_from,
                'effective_until' => $document->effective_until ?? $source->effective_until,
            ],
            baseMetadata: array_filter($metadata, fn ($v) => $v !== null && $v !== ''),
            embeddingSignature: $base->embeddingSignature(),
        );
    }

    /**
     * An FAQ is one chunk holding question and answer together.
     *
     * @return array{written: int, reused: int, removed: int}
     */
    public function syncFaqChunks(KnowledgeFaq $faq): array
    {
        $source = $faq->source;
        $base = $faq->knowledgeBase;

        // A withdrawn FAQ must leave the index immediately, not at the next
        // scheduled anything.
        if (! $faq->status->isRetrievable()) {
            $removed = KnowledgeChunk::query()->where('owner_key', $faq->chunkOwnerKey())->delete();
            $this->statistics->refreshForBase($base);

            return ['written' => 0, 'reused' => 0, 'removed' => $removed];
        }

        $text = $faq->retrievableText();

        $candidates = [new ChunkCandidate(0, $text, [
            'faq_question' => $faq->question,
            'document_name' => Str::limit($faq->question, 80),
            'section' => $faq->category,
            'collection' => $faq->collection?->name,
            'last_updated' => $faq->updated_at?->toDateString(),
        ])];

        return $this->sync(
            ownerKey: $faq->chunkOwnerKey(),
            candidates: $candidates,
            columns: [
                'knowledge_base_id' => $faq->knowledge_base_id,
                'knowledge_source_id' => $faq->knowledge_source_id,
                'knowledge_collection_id' => $faq->knowledge_collection_id,
                'knowledge_document_id' => null,
                'knowledge_website_page_id' => null,
                'knowledge_faq_id' => $faq->id,
                'source_type' => SourceType::Faq->value,
                'priority' => $source->priority->value,
                'language' => $faq->language,
                'effective_from' => $faq->effective_from,
                'effective_until' => $faq->effective_until,
            ],
            baseMetadata: array_filter([
                'faq_question' => $faq->question,
                'section' => $faq->category,
                'collection' => $faq->collection?->name,
            ], fn ($v) => $v !== null && $v !== ''),
            embeddingSignature: $base->embeddingSignature(),
        );
    }

    /**
     * @param  array<int, ChunkCandidate>  $candidates
     * @param  array<string, mixed>  $columns
     * @param  array<string, mixed>  $baseMetadata
     * @return array{written: int, reused: int, removed: int}
     */
    private function sync(
        string $ownerKey,
        array $candidates,
        array $columns,
        array $baseMetadata,
        string $embeddingSignature,
    ): array {
        // Existing hashes tell us which chunks are genuinely new. A chunk whose
        // text is unchanged AND whose vector was produced under the current
        // model signature keeps its embedding — that is what makes a re-crawl
        // of a mostly-unchanged site nearly free.
        $existing = KnowledgeChunk::query()
            ->where('owner_key', $ownerKey)
            ->get(['id', 'chunk_index', 'content_hash', 'embedding_provider', 'embedding_model', 'embedding_dimensions', 'embedding_version', 'embedding_status'])
            ->keyBy('chunk_index');

        $reused = 0;
        $rows = [];
        $now = now();

        foreach ($candidates as $candidate) {
            $hash = $candidate->hash();
            $previous = $existing->get($candidate->index);

            $vectorStillValid = $previous
                && $previous->content_hash === $hash
                && $previous->embedding_status === KnowledgeChunk::EMBEDDING_READY
                && $this->signatureOf($previous) === $embeddingSignature;

            if ($vectorStillValid) {
                $reused++;
            }

            $rows[] = array_merge($columns, [
                'uuid' => (string) Str::uuid(),
                'owner_key' => $ownerKey,
                'chunk_index' => $candidate->index,
                'content' => $candidate->content,
                'content_hash' => $hash,
                'token_count' => $candidate->tokenCount ?? 0,
                'character_count' => $candidate->characterCount(),
                'metadata' => json_encode(array_merge($baseMetadata, $candidate->metadata)),
                // Pending until embedded; the embedding job flips it to ready.
                // Not retrievable until then, so a half-embedded document never
                // answers with a subset of itself.
                'embedding_status' => $vectorStillValid
                    ? KnowledgeChunk::EMBEDDING_READY
                    : KnowledgeChunk::EMBEDDING_PENDING,
                'is_retrievable' => $vectorStillValid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $removed = 0;

        DB::transaction(function () use ($ownerKey, $rows, $candidates, &$removed): void {
            foreach (array_chunk($rows, 200) as $batch) {
                KnowledgeChunk::query()->upsert(
                    $batch,
                    ['owner_key', 'chunk_index'],
                    // uuid and the raw embedding binary are intentionally
                    // absent: an upsert must not mint a new uuid for an
                    // existing chunk (breaking citation links), and the vector
                    // for content that is unchanged must survive untouched.
                    // embedding_status/is_retrievable MUST be written, though:
                    // each row above already computed the correct pending/
                    // not-retrievable pair for changed content, and omitting
                    // them here left a changed chunk's stale `ready`/true
                    // values in place, silently serving an outdated vector
                    // for content that no longer matches it.
                    [
                        'knowledge_collection_id', 'content', 'content_hash', 'token_count',
                        'character_count', 'metadata', 'language', 'priority', 'source_type',
                        'effective_from', 'effective_until', 'embedding_status', 'is_retrievable',
                        'updated_at',
                    ]
                );
            }

            // Rows beyond the new chunk count are leftovers from a longer
            // previous version and must go, or the document keeps answering
            // with content it no longer contains.
            $removed = KnowledgeChunk::query()
                ->where('owner_key', $ownerKey)
                ->where('chunk_index', '>=', count($candidates))
                ->delete();

            // Chunks whose text changed need a fresh vector; the old one
            // describes different content and must stop serving immediately.
            KnowledgeChunk::query()
                ->where('owner_key', $ownerKey)
                ->where('embedding_status', KnowledgeChunk::EMBEDDING_PENDING)
                ->update(['is_retrievable' => false, 'embedding' => null]);
        });

        return ['written' => count($rows), 'reused' => $reused, 'removed' => $removed];
    }

    private function signatureOf(KnowledgeChunk $chunk): string
    {
        return "{$chunk->embedding_provider}:{$chunk->embedding_model}:{$chunk->embedding_dimensions}:v{$chunk->embedding_version}";
    }

    /**
     * Takes a source's chunks out of retrieval without deleting them.
     *
     * Used when a source is disabled, archived or soft-deleted. The rows survive
     * for audit and restore; retrieval stops seeing them on the next query,
     * which is the guarantee the spec requires of a deleted knowledge resource.
     */
    public function withdrawSource(KnowledgeSource $source): int
    {
        return KnowledgeChunk::query()
            ->where('knowledge_source_id', $source->id)
            ->update(['is_retrievable' => false]);
    }

    public function restoreSource(KnowledgeSource $source): int
    {
        return KnowledgeChunk::query()
            ->where('knowledge_source_id', $source->id)
            ->where('embedding_status', KnowledgeChunk::EMBEDDING_READY)
            ->update(['is_retrievable' => true]);
    }

    /** Withdraws a single document's chunks (archive, replace, soft delete). */
    public function withdrawDocument(KnowledgeDocument $document): int
    {
        return KnowledgeChunk::query()
            ->where('owner_key', $document->chunkOwnerKey())
            ->update(['is_retrievable' => false]);
    }

    /**
     * Forces every chunk in a base to be re-embedded, e.g. after the embedding
     * model changed. Vectors are cleared rather than left in place: a stale
     * vector of the wrong width or from the wrong model would produce
     * meaningless similarity scores if it were ever compared.
     */
    public function markBaseForReindex(int $knowledgeBaseId): int
    {
        return KnowledgeChunk::query()
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->update([
                'embedding' => null,
                'embedding_status' => KnowledgeChunk::EMBEDDING_PENDING,
                'is_retrievable' => false,
                'updated_at' => now(),
            ]);
    }
}
