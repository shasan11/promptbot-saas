<?php

namespace App\Contracts\Knowledge;

use App\Services\Knowledge\Data\RetrievalQuery;

interface VectorStoreInterface
{
    /**
     * Nearest neighbours to `$queryVector`, restricted to what the query's
     * allow-lists permit.
     *
     * Implementations MUST apply the permission filters as part of the search
     * itself, not to its output. Any driver that cannot express the filters
     * natively must fetch only from within the allowed scope.
     *
     * @param  array<int, float>  $queryVector
     * @return array<int, array{chunk_id: int, score: float}>  Best-first.
     */
    public function search(RetrievalQuery $query, array $queryVector, int $limit): array;

    /** Chunks whose stored vectors were produced under a different model signature. */
    public function countStaleVectors(int $knowledgeBaseId, string $signature): int;

    public function name(): string;
}
