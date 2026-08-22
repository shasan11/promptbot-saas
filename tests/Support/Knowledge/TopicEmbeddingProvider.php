<?php

namespace Tests\Support\Knowledge;

use App\Contracts\Knowledge\EmbeddingProviderInterface;
use App\Services\Knowledge\Data\EmbeddingResult;

/**
 * A deterministic embedding provider that places text on a small set of named
 * topic axes.
 *
 * Retrieval admission is a question about *distance* — does this passage sit
 * near the question, or somewhere else entirely? Testing that with a real
 * model would make the assertion depend on an API call and on whatever
 * similarity numbers that model happens to produce this week. Here, a text
 * about refunds and a question about refunds land on the same axis (cosine
 * ~1.0) and a question about baking lands on an orthogonal one (cosine ~0.0),
 * so "relevant" and "off-topic" are unambiguous and the only thing under test
 * is the service's decision.
 */
class TopicEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * Word lists per axis. Order fixes the vector layout, so it must stay
     * stable within a test run.
     *
     * @var array<string, array<int, string>>
     */
    private const AXES = [
        'refunds' => ['refund', 'refunds', 'refunded', 'order', 'orders', 'policy', 'purchase'],
        'shipping' => ['shipping', 'delivery', 'courier', 'tracking', 'parcel'],
        'baking' => ['sourdough', 'bread', 'recipe', 'bake', 'baking', 'flour', 'oven'],
    ];

    /** The extra axis every text gets a small constant on, so no vector is all zeros. */
    private const BASELINE = 0.01;

    public function embedBatch(array $texts): EmbeddingResult
    {
        return new EmbeddingResult(
            vectors: array_map(fn (string $text) => $this->vector($text), array_values($texts)),
            provider: $this->name(),
            model: $this->model(),
            dimensions: $this->dimensions(),
            tokensUsed: count($texts) * 8,
            estimatedCost: 0.0,
            latencyMs: 1,
        );
    }

    public function embed(string $text): EmbeddingResult
    {
        return $this->embedBatch([$text]);
    }

    /** @return array<int, float> */
    private function vector(string $text): array
    {
        $words = preg_split('/[^a-z]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $vector = [];

        foreach (self::AXES as $terms) {
            $vector[] = (float) count(array_intersect($words, $terms));
        }

        $vector[] = self::BASELINE;

        return $vector;
    }

    public function name(): string
    {
        return 'topic-test';
    }

    public function model(): string
    {
        return 'topic-test-model';
    }

    public function dimensions(): int
    {
        return count(self::AXES) + 1;
    }

    public function maxBatchSize(): int
    {
        return 128;
    }

    public function estimateCost(int $tokens): float
    {
        return 0.0;
    }
}
