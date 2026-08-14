<?php

namespace App\Services\AI\Data;

final class EmbedResult
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
}
