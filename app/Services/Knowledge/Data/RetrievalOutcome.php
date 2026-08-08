<?php

namespace App\Services\Knowledge\Data;

/**
 * The full result of a retrieval request: the hits that made the cut, the ones
 * that did not (for the debugger), timing, and the assembled context string.
 */
final class RetrievalOutcome
{
    /**
     * @param  array<int, RetrievalHit>  $hits      Ranked, threshold-passing, context-included.
     * @param  array<int, RetrievalHit>  $discarded Candidates rejected by threshold or token budget.
     * @param  array<string, int>  $timings
     */
    public function __construct(
        public readonly array $hits,
        public readonly array $discarded = [],
        public readonly array $timings = [],
        public readonly int $semanticCandidates = 0,
        public readonly int $keywordCandidates = 0,
        public readonly string $context = '',
        public readonly int $contextTokens = 0,
        public readonly ?string $logUuid = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->hits === [];
    }

    public function topScore(): ?float
    {
        return $this->hits ? $this->hits[0]->finalScore : null;
    }

    public function averageScore(): ?float
    {
        if (! $this->hits) {
            return null;
        }

        return array_sum(array_map(fn (RetrievalHit $hit) => $hit->finalScore, $this->hits)) / count($this->hits);
    }

    /** @return array<int, array<string, mixed>> */
    public function citations(): array
    {
        // Deduplicated by source document: three chunks from the same PDF are
        // one citation, not three.
        $seen = [];
        $citations = [];

        foreach ($this->hits as $hit) {
            $citation = $hit->chunk->citation();
            $key = ($citation['url'] ?? '').'|'.($citation['document_title'] ?? '').'|'.($citation['page'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $citations[] = $citation;
        }

        return $citations;
    }
}
