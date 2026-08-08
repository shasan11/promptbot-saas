<?php

namespace App\Services\Knowledge\Retrieval;

use App\Contracts\Knowledge\VectorStoreInterface;
use App\Models\Knowledge\KnowledgeChunk;
use App\Services\Knowledge\Data\RetrievalQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Exact nearest-neighbour search over vectors stored in the tenant's own MySQL
 * database.
 *
 * PromptBot runs on MySQL, so pgvector is not available. Rather than bolt on a
 * separate vector database that every install would then have to operate, this
 * driver streams the *permission-filtered* candidate set and scores it in PHP.
 *
 * The trade-off, stated honestly:
 *   + exact results — no approximate-index recall loss, no index to rebuild
 *   + no extra infrastructure, and vectors inherit tenant DB isolation for free
 *   − cost is linear in candidates. Roughly 1536-dim float32 at ~20k candidates
 *     is tens of milliseconds; it is not viable at millions.
 *
 * `knowledge.vector_store.max_candidates` bounds the work per query. Past that
 * scale, implement VectorStoreInterface against Qdrant/pgvector — nothing above
 * this class knows which driver is in use.
 *
 * Security note: the metadata filters are applied in the SQL that selects
 * candidates, not to the scored output. A chunk the actor may not read is never
 * loaded, never scored, and cannot influence result counts or timing.
 */
class DatabaseVectorStore implements VectorStoreInterface
{
    public function search(RetrievalQuery $query, array $queryVector, int $limit): array
    {
        if ($queryVector === [] || $query->isEmptyScope()) {
            return [];
        }

        $dimensions = count($queryVector);
        // Cosine similarity reduces to a dot product once both sides are unit
        // length, so the query vector is normalised once here rather than
        // dividing by its magnitude for every candidate.
        $normalisedQuery = $this->normalise($queryVector);

        $scanSize = (int) config('knowledge.vector_store.scan_chunk_size');
        $maxCandidates = (int) config('knowledge.vector_store.max_candidates');

        $heap = [];
        $scanned = 0;
        $lastId = 0;

        while ($scanned < $maxCandidates) {
            $rows = $this->candidateQuery($query)
                ->where('knowledge_chunks.id', '>', $lastId)
                ->orderBy('knowledge_chunks.id')
                ->limit(min($scanSize, $maxCandidates - $scanned))
                ->get(['knowledge_chunks.id', 'knowledge_chunks.embedding', 'knowledge_chunks.embedding_dimensions']);

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = (int) $row->id;
                $scanned++;

                // Width mismatch means this chunk was embedded under a different
                // model. Comparing them would produce a plausible number from
                // incomparable spaces, so it is skipped and left for re-indexing.
                if ((int) $row->embedding_dimensions !== $dimensions) {
                    continue;
                }

                $vector = KnowledgeChunk::unpackVector($this->readBlob($row->embedding));

                if (count($vector) !== $dimensions) {
                    continue;
                }

                $score = $this->dot($normalisedQuery, $vector);
                $heap[] = ['chunk_id' => (int) $row->id, 'score' => $score];
            }

            if ($rows->count() < $scanSize) {
                break;
            }
        }

        usort($heap, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($heap, 0, $limit);
    }

    /**
     * The candidate set: everything the actor may read, that is live, embedded,
     * and in its effective window. Every restriction is expressed here.
     */
    private function candidateQuery(RetrievalQuery $query): Builder
    {
        $builder = DB::table('knowledge_chunks')
            ->join('knowledge_bases', 'knowledge_bases.id', '=', 'knowledge_chunks.knowledge_base_id')
            ->whereIn('knowledge_chunks.knowledge_base_id', $query->knowledgeBaseIds)
            ->where('knowledge_chunks.is_retrievable', true)
            ->where('knowledge_chunks.embedding_status', KnowledgeChunk::EMBEDDING_READY)
            ->whereNotNull('knowledge_chunks.embedding')
            // A soft-deleted or disabled base must stop answering immediately,
            // even though its chunk rows still exist.
            ->whereNull('knowledge_bases.deleted_at')
            ->whereIn('knowledge_bases.status', ['active', 'processing', 'warning']);

        // null = unrestricted; [] would have been rejected by isEmptyScope().
        if ($query->allowedCollectionIds !== null) {
            $builder->whereIn('knowledge_chunks.knowledge_collection_id', $query->allowedCollectionIds);
        }

        if ($query->sourceIds) {
            $builder->whereIn('knowledge_chunks.knowledge_source_id', $query->sourceIds);
        }

        if ($query->language) {
            $builder->where(fn (Builder $q) => $q
                ->where('knowledge_chunks.language', $query->language)
                ->orWhereNull('knowledge_chunks.language'));
        }

        if ($query->excludeExpired) {
            $now = now();
            $builder
                ->where(fn (Builder $q) => $q->whereNull('knowledge_chunks.effective_from')->orWhere('knowledge_chunks.effective_from', '<=', $now))
                ->where(fn (Builder $q) => $q->whereNull('knowledge_chunks.effective_until')->orWhere('knowledge_chunks.effective_until', '>=', $now));
        }

        if ($query->tags) {
            $builder->whereExists(fn (Builder $q) => $q
                ->from('knowledge_taggables')
                ->join('knowledge_tags', 'knowledge_tags.id', '=', 'knowledge_taggables.knowledge_tag_id')
                ->whereColumn('knowledge_taggables.taggable_id', 'knowledge_chunks.knowledge_source_id')
                ->where('knowledge_taggables.taggable_type', \App\Models\Knowledge\KnowledgeSource::class)
                ->whereIn('knowledge_tags.slug', $query->tags));
        }

        return $builder;
    }

    public function countStaleVectors(int $knowledgeBaseId, string $signature): int
    {
        [$provider, $model, $dimensions, $version] = array_pad(explode(':', $signature), 4, null);

        return KnowledgeChunk::query()
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->where(fn ($q) => $q
                ->where('embedding_provider', '!=', $provider)
                ->orWhere('embedding_model', '!=', $model)
                ->orWhere('embedding_dimensions', '!=', (int) $dimensions)
                ->orWhere('embedding_version', '!=', (int) ltrim((string) $version, 'v'))
                ->orWhereNull('embedding'))
            ->count();
    }

    public function name(): string
    {
        return 'database';
    }

    /** PDO may hand back a stream for a BLOB depending on driver settings. */
    private function readBlob(mixed $value): ?string
    {
        if (is_resource($value)) {
            return stream_get_contents($value) ?: null;
        }

        return is_string($value) ? $value : null;
    }

    /** @param  array<int, float>  $vector  @return array<int, float> */
    private function normalise(array $vector): array
    {
        $magnitude = 0.0;

        foreach ($vector as $value) {
            $magnitude += $value * $value;
        }

        $magnitude = sqrt($magnitude);

        if ($magnitude <= 0.0) {
            return $vector;
        }

        foreach ($vector as $i => $value) {
            $vector[$i] = $value / $magnitude;
        }

        return $vector;
    }

    /**
     * Dot product against an already-normalised query vector.
     *
     * Stored vectors are normalised at write time by every provider we ship, but
     * a third-party provider might not be, so the candidate's magnitude is
     * folded in here — that keeps this a true cosine rather than assuming a
     * property the interface does not require.
     *
     * @param  array<int, float>  $query
     * @param  array<int, float>  $candidate
     */
    private function dot(array $query, array $candidate): float
    {
        $dot = 0.0;
        $candidateMagnitude = 0.0;

        foreach ($query as $i => $value) {
            $other = $candidate[$i];
            $dot += $value * $other;
            $candidateMagnitude += $other * $other;
        }

        $candidateMagnitude = sqrt($candidateMagnitude);

        return $candidateMagnitude > 0.0 ? $dot / $candidateMagnitude : 0.0;
    }
}
