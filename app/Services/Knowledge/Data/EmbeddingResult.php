<?php

namespace App\Services\Knowledge\Data;

/**
 * One provider call's worth of vectors, plus the accounting the cost tracker
 * and the analytics page need.
 */
final class EmbeddingResult
{
    /** @param  array<int, array<int, float>>  $vectors  Parallel to the input texts. */
    public function __construct(
        public readonly array $vectors,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $dimensions,
        public readonly int $tokensUsed = 0,
        public readonly float $estimatedCost = 0.0,
        public readonly int $latencyMs = 0,
    ) {}

    public function count(): int
    {
        return count($this->vectors);
    }

    public function signature(): string
    {
        return "{$this->provider}:{$this->model}:{$this->dimensions}";
    }
}
