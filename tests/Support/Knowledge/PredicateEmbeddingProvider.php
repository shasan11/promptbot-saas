<?php

namespace Tests\Support\Knowledge;

use App\Contracts\Knowledge\EmbeddingProviderInterface;
use App\Exceptions\Knowledge\EmbeddingException;
use App\Services\Knowledge\Data\EmbeddingResult;

/**
 * A deterministic test double that fails embedding for any text a caller-
 * supplied predicate matches, and otherwise succeeds with a fixed-width
 * vector. Lets tests exercise real partial-failure / retry behaviour in
 * EmbeddingService without a network dependency.
 */
class PredicateEmbeddingProvider implements EmbeddingProviderInterface
{
    /** @param  callable(string): bool  $shouldFail */
    public function __construct(
        private readonly int $dimensions,
        private $shouldFail,
        private readonly bool $retryable = false,
    ) {}

    public function embedBatch(array $texts): EmbeddingResult
    {
        foreach ($texts as $text) {
            if (($this->shouldFail)($text)) {
                throw $this->retryable
                    ? EmbeddingException::rateLimited($this->name())
                    : EmbeddingException::unauthorised($this->name());
            }
        }

        return new EmbeddingResult(
            vectors: array_map(fn () => array_fill(0, $this->dimensions, 0.05), $texts),
            provider: $this->name(),
            model: 'predicate-test-model',
            dimensions: $this->dimensions,
            tokensUsed: count($texts) * 5,
            estimatedCost: 0.0,
            latencyMs: 1,
        );
    }

    public function embed(string $text): EmbeddingResult
    {
        return $this->embedBatch([$text]);
    }

    public function name(): string
    {
        return 'predicate-test';
    }

    public function model(): string
    {
        return 'predicate-test-model';
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
}
