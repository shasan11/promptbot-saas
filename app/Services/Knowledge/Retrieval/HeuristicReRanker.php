<?php

namespace App\Services\Knowledge\Retrieval;

use App\Contracts\Knowledge\ReRankerInterface;
use App\Services\Knowledge\Data\RetrievalHit;

/**
 * Re-ranking without a cross-encoder.
 *
 * A proper re-ranker runs a model over every (query, chunk) pair — accurate, and
 * a per-query API bill. This applies cheap signals that correlate with the same
 * judgements, so it improves ordering at no cost and can be swapped for a real
 * ReRankerInterface later without touching the retrieval service.
 *
 * The signals, and why each is bounded small: they adjust ordering *within* a
 * set of already-relevant candidates. Letting any of them dominate would push a
 * semantically irrelevant chunk to the top because it happened to be
 * authoritative or recent, which is worse than the ordering they fix.
 */
class HeuristicReRanker implements ReRankerInterface
{
    public function rerank(string $query, array $hits): array
    {
        if (! $hits) {
            return [];
        }

        $queryTerms = $this->terms($query);

        foreach ($hits as $hit) {
            $score = $hit->fusedScore;

            // 1. Term coverage. A chunk containing every word of the question
            //    usually answers it; one matching a third of it often does not.
            $coverage = $this->coverage($queryTerms, $hit->chunk->content);
            $score *= 1.0 + ($coverage * 0.15);

            // 2. Source priority. An official policy outranks meeting notes
            //    that happen to discuss the same subject.
            $score *= $hit->chunk->priority?->rankingWeight() ?? 1.0;

            // 3. Length sanity. Very short chunks are usually headings or
            //    fragments; very long ones dilute their own embedding.
            $score *= $this->lengthFactor($hit->chunk->token_count ?? 0);

            // 4. Exact phrase. Strong evidence, and the one signal allowed a
            //    meaningful boost — a literal quote of the question is rarely
            //    a coincidence.
            if ($this->containsPhrase($query, $hit->chunk->content)) {
                $score *= 1.12;
            }

            $hit->finalScore = $score;
        }

        usort($hits, fn (RetrievalHit $a, RetrievalHit $b) => $b->finalScore <=> $a->finalScore);

        foreach ($hits as $index => $hit) {
            $hit->rank = $index + 1;
        }

        return $hits;
    }

    public function name(): string
    {
        return 'heuristic';
    }

    /** @param  array<int, string>  $queryTerms */
    private function coverage(array $queryTerms, string $content): float
    {
        if (! $queryTerms) {
            return 0.0;
        }

        $haystack = mb_strtolower($content);
        $found = 0;

        foreach ($queryTerms as $term) {
            if (str_contains($haystack, $term)) {
                $found++;
            }
        }

        return $found / count($queryTerms);
    }

    /**
     * Peaks around a mid-size chunk and tapers either side, bounded to
     * [0.85, 1.0] so length never overturns relevance on its own.
     */
    private function lengthFactor(int $tokens): float
    {
        return match (true) {
            $tokens < 30 => 0.85,
            $tokens < 80 => 0.95,
            $tokens > 900 => 0.92,
            default => 1.0,
        };
    }

    private function containsPhrase(string $query, string $content): bool
    {
        $phrase = trim(preg_replace('/[^\p{L}\p{N}\s]+/u', '', mb_strtolower($query)) ?? '');

        // Two words is not a phrase; it is a coincidence waiting to happen.
        if (count(preg_split('/\s+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY) ?: []) < 3) {
            return false;
        }

        return str_contains(mb_strtolower(preg_replace('/\s+/u', ' ', $content) ?? ''), $phrase);
    }

    /** @return array<int, string> */
    private function terms(string $query): array
    {
        $stopWords = ['the', 'and', 'for', 'are', 'can', 'you', 'our', 'how', 'what', 'when', 'does', 'with', 'from', 'that', 'this'];
        $words = preg_split('/\W+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $w) => mb_strlen($w) > 2 && ! in_array($w, $stopWords, true)
        )));
    }
}
