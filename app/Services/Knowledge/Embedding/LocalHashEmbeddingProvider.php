<?php

namespace App\Services\Knowledge\Embedding;

use App\Contracts\Knowledge\EmbeddingProviderInterface;
use App\Services\Knowledge\Data\EmbeddingResult;
use App\Services\Knowledge\Support\TokenEstimator;

/**
 * A dependency-free, deterministic embedding provider.
 *
 * This ships as the default so a fresh install — and CI — can run the entire
 * ingest → index → retrieve path with no API key and no network. It produces
 * real, stable, normalised vectors using the hashing trick over word unigrams
 * and bigrams, which makes lexical and near-lexical matching work properly.
 *
 * What it is NOT is semantically strong: it has no notion that "refund" and
 * "money back" mean the same thing, because there is no trained model behind
 * it. Any production workspace should switch to a real provider in Knowledge
 * settings and re-index. The UI labels it accordingly rather than letting an
 * operator assume they are getting semantic search when they are not.
 */
class LocalHashEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private readonly int $dimensions = 384,
        private readonly string $model = 'promptbot-local-hash-v1',
    ) {}

    public function embedBatch(array $texts): EmbeddingResult
    {
        $startedAt = hrtime(true);
        $vectors = [];
        $tokens = 0;

        foreach ($texts as $text) {
            $vectors[] = $this->vectorise($text);
            $tokens += TokenEstimator::estimate($text);
        }

        return new EmbeddingResult(
            vectors: $vectors,
            provider: $this->name(),
            model: $this->model,
            dimensions: $this->dimensions,
            tokensUsed: $tokens,
            estimatedCost: 0.0,
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
        );
    }

    public function embed(string $text): EmbeddingResult
    {
        return $this->embedBatch([$text]);
    }

    public function name(): string
    {
        return 'local';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function maxBatchSize(): int
    {
        return 512;
    }

    public function estimateCost(int $tokens): float
    {
        return 0.0;
    }

    /**
     * Hashing trick: each term is hashed to a dimension and accumulated with a
     * sign derived from a second hash (so unrelated collisions cancel rather
     * than compound). Sub-linear term weighting damps the effect of a word
     * repeated many times, and the vector is L2-normalised so cosine similarity
     * reduces to a dot product.
     *
     * @return array<int, float>
     */
    private function vectorise(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);
        $terms = $this->tokenise($text);

        if (! $terms) {
            return $vector;
        }

        $frequencies = array_count_values($terms);

        // Bigrams give the representation a little word-order sensitivity,
        // which is what stops "dogs bite men" and "men bite dogs" colliding.
        for ($i = 0, $n = count($terms) - 1; $i < $n; $i++) {
            $bigram = $terms[$i].'_'.$terms[$i + 1];
            $frequencies[$bigram] = ($frequencies[$bigram] ?? 0) + 1;
        }

        foreach ($frequencies as $term => $count) {
            $primary = crc32((string) $term);
            $index = $primary % $this->dimensions;
            $sign = (crc32('sign:'.$term) % 2) === 0 ? 1.0 : -1.0;

            $vector[$index] += $sign * (1.0 + log((float) $count));
        }

        return $this->normalise($vector);
    }

    /** @return array<int, string> */
    private function tokenise(string $text): array
    {
        $lowered = mb_strtolower($text);
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $lowered) ?? '';
        $terms = preg_split('/\s+/u', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($terms, fn (string $term) => mb_strlen($term) > 1));
    }

    /** @param  array<int, float>  $vector  @return array<int, float> */
    private function normalise(array $vector): array
    {
        $magnitude = sqrt(array_sum(array_map(fn (float $v) => $v * $v, $vector)));

        if ($magnitude <= 0.0) {
            return $vector;
        }

        return array_map(fn (float $v) => $v / $magnitude, $vector);
    }
}
