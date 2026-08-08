<?php

namespace App\Services\Knowledge\Retrieval;

use App\Models\Knowledge\KnowledgeChunk;
use App\Services\Knowledge\Data\RetrievalQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The keyword half of hybrid retrieval, on MySQL full-text.
 *
 * Semantic search is weak at exactly the things support teams search for most:
 * error codes, SKUs, plan names, version numbers. "ERR_4021" has no meaningful
 * embedding neighbourhood — it either matches literally or it does not. This
 * covers that half.
 *
 * Applies the identical permission filters as the vector store; the two halves
 * of a hybrid query must never disagree about what the actor may see.
 */
class KeywordSearcher
{
    /**
     * @return array<int, array{chunk_id: int, score: float}>
     */
    public function search(RetrievalQuery $query, int $limit): array
    {
        if ($query->isEmptyScope()) {
            return [];
        }

        $terms = $this->booleanExpression($query->normalisedQuery());

        if ($terms === '') {
            return [];
        }

        try {
            $rows = $this->candidateQuery($query)
                ->selectRaw(
                    'knowledge_chunks.id as chunk_id, MATCH(knowledge_chunks.content) AGAINST (? IN BOOLEAN MODE) as relevance',
                    [$terms]
                )
                ->whereRaw('MATCH(knowledge_chunks.content) AGAINST (? IN BOOLEAN MODE)', [$terms])
                ->orderByDesc('relevance')
                ->limit($limit)
                ->get();
        } catch (Throwable) {
            // Full-text is unavailable on some hosted MySQL variants and on
            // installs whose tables predate the index. Degrading to LIKE keeps
            // hybrid retrieval working (worse, but working) rather than failing
            // the whole query.
            return $this->likeFallback($query, $limit);
        }

        if ($rows->isEmpty()) {
            return [];
        }

        // MySQL relevance is an unbounded TF-IDF-ish score. Normalising against
        // the best hit puts it on 0..1 so it can be fused with cosine
        // similarity, which lives on that scale.
        $best = max(0.000001, (float) $rows->first()->relevance);

        return $rows->map(fn ($row) => [
            'chunk_id' => (int) $row->chunk_id,
            'score' => min(1.0, (float) $row->relevance / $best),
        ])->all();
    }

    /**
     * Builds a BOOLEAN MODE expression.
     *
     * Every term is escaped and wrapped so that user input cannot inject
     * operators. Terms are optional rather than required (`+`) because
     * demanding every word of a natural-language question would return nothing
     * for most real queries.
     */
    private function booleanExpression(string $query): string
    {
        $cleaned = preg_replace('/[+\-><\(\)~*\"@]+/', ' ', $query) ?? '';
        $words = preg_split('/\s+/u', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];

        foreach ($words as $word) {
            // MySQL's default ft_min_word_len is 3; shorter tokens match nothing
            // and only dilute the expression.
            if (mb_strlen($word) < 3) {
                continue;
            }

            $terms[] = '"'.str_replace('"', '', $word).'"';
        }

        return implode(' ', array_slice($terms, 0, 32));
    }

    /** @return array<int, array{chunk_id: int, score: float}> */
    private function likeFallback(RetrievalQuery $query, int $limit): array
    {
        $words = array_slice(array_filter(
            preg_split('/\s+/u', mb_strtolower($query->normalisedQuery())) ?: [],
            fn (string $w) => mb_strlen($w) >= 3
        ), 0, 8);

        if (! $words) {
            return [];
        }

        $builder = $this->candidateQuery($query);

        $builder->where(function (Builder $inner) use ($words): void {
            foreach ($words as $word) {
                $inner->orWhere('knowledge_chunks.content', 'like', '%'.$this->escapeLike($word).'%');
            }
        });

        $rows = $builder->limit($limit)->get(['knowledge_chunks.id as chunk_id', 'knowledge_chunks.content']);

        return $rows->map(function ($row) use ($words) {
            $content = mb_strtolower((string) $row->content);
            $matched = count(array_filter($words, fn (string $w) => str_contains($content, $w)));

            return [
                'chunk_id' => (int) $row->chunk_id,
                // Proportion of query words present — crude, but on the same
                // 0..1 scale as the full-text path so fusion still works.
                'score' => $matched / count($words),
            ];
        })->sortByDesc('score')->values()->all();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /**
     * Identical scope to DatabaseVectorStore::candidateQuery(). Kept in step
     * deliberately — if the two halves of hybrid retrieval disagreed on
     * visibility, the keyword half would become a way to read chunks the
     * semantic half correctly withholds.
     */
    private function candidateQuery(RetrievalQuery $query): Builder
    {
        $builder = DB::table('knowledge_chunks')
            ->join('knowledge_bases', 'knowledge_bases.id', '=', 'knowledge_chunks.knowledge_base_id')
            ->whereIn('knowledge_chunks.knowledge_base_id', $query->knowledgeBaseIds)
            ->where('knowledge_chunks.is_retrievable', true)
            ->where('knowledge_chunks.embedding_status', KnowledgeChunk::EMBEDDING_READY)
            ->whereNull('knowledge_bases.deleted_at')
            ->whereIn('knowledge_bases.status', ['active', 'processing', 'warning']);

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

        return $builder;
    }
}
