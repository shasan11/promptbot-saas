<?php

namespace App\Services\Knowledge\Retrieval;

use App\Contracts\Knowledge\ReRankerInterface;
use App\Services\Knowledge\Data\RetrievalHit;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Re-ranking through a hosted cross-encoder (Cohere Rerank, Jina Reranker,
 * Voyage — all three expose the same request shape).
 *
 * `HeuristicReRanker` reorders using lexical signals: term coverage, exact
 * phrase, source priority, length. Those correlate with relevance but cannot
 * see meaning, so a chunk that answers the question in different words ranks
 * below one that merely repeats its vocabulary. A cross-encoder reads the
 * query and the chunk together and scores actual relevance, which is the one
 * thing the heuristic fundamentally cannot do.
 *
 * It costs an API call per query, so it is opt-in via
 * `knowledge.retrieval.reranking.driver`. Any failure — misconfiguration,
 * timeout, provider outage, unexpected payload — falls back to the heuristic
 * rather than propagating: degraded ordering is a far better outcome than a
 * customer question erroring out because a reranking vendor is down.
 */
class ProviderReRanker implements ReRankerInterface
{
    public function __construct(private readonly HeuristicReRanker $fallback) {}

    public function rerank(string $query, array $hits): array
    {
        if ($hits === []) {
            return [];
        }

        $endpoint = (string) config('knowledge.retrieval.reranking.endpoint');
        $apiKey = (string) config('knowledge.retrieval.reranking.api_key');

        if ($endpoint === '' || $apiKey === '') {
            return $this->fallback->rerank($query, $hits);
        }

        try {
            $scores = $this->score($query, $hits, $endpoint, $apiKey);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallback->rerank($query, $hits);
        }

        if ($scores === []) {
            return $this->fallback->rerank($query, $hits);
        }

        foreach ($hits as $index => $hit) {
            // A candidate the provider did not return a score for keeps its
            // fused score rather than dropping to zero — absence of a score
            // is missing information, not evidence of irrelevance.
            $hit->finalScore = $scores[$index] ?? $hit->fusedScore;
        }

        usort($hits, fn (RetrievalHit $a, RetrievalHit $b) => $b->finalScore <=> $a->finalScore);

        foreach ($hits as $index => $hit) {
            $hit->rank = $index + 1;
        }

        return $hits;
    }

    /**
     * @param  array<int, RetrievalHit>  $hits
     * @return array<int, float>  Relevance keyed by the hit's original index.
     */
    private function score(string $query, array $hits, string $endpoint, string $apiKey): array
    {
        $documents = array_map(
            // Truncated: rerankers charge by token and degrade on very long
            // passages, and the answer-bearing text is near the top anyway.
            fn (RetrievalHit $hit) => mb_substr($hit->chunk->content, 0, 2000),
            $hits,
        );

        $response = Http::withToken($apiKey)
            ->asJson()
            ->timeout((int) config('knowledge.retrieval.reranking.timeout', 8))
            ->post($endpoint, [
                'model' => (string) config('knowledge.retrieval.reranking.model'),
                'query' => $query,
                'documents' => $documents,
                'top_n' => count($documents),
            ]);

        if ($response->failed()) {
            return [];
        }

        $scores = [];

        // Cohere/Jina/Voyage all answer with `results: [{index, relevance_score}]`.
        foreach ((array) $response->json('results', []) as $row) {
            if (! isset($row['index'])) {
                continue;
            }

            $scores[(int) $row['index']] = (float) ($row['relevance_score'] ?? $row['score'] ?? 0.0);
        }

        return $scores;
    }

    public function name(): string
    {
        return 'provider';
    }
}
