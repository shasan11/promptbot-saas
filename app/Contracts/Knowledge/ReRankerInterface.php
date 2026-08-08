<?php

namespace App\Contracts\Knowledge;

use App\Services\Knowledge\Data\RetrievalHit;

interface ReRankerInterface
{
    /**
     * Re-orders candidates against the query and writes the new ordering into
     * each hit's `finalScore` and `rank`.
     *
     * @param  array<int, RetrievalHit>  $hits
     * @return array<int, RetrievalHit>  Same hits, re-sorted best-first.
     */
    public function rerank(string $query, array $hits): array;

    public function name(): string;
}
