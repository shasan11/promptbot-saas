<?php

namespace Tests\Support\Knowledge;

use App\Contracts\Knowledge\EmbeddingProviderInterface;
use App\Services\Knowledge\Data\EmbeddingResult;
use RuntimeException;

/**
 * Always throws a plain, unwrapped exception — simulates a provider driver
 * bug or config error that was never translated into an EmbeddingException,
 * so tests can verify EmbeddingService doesn't let it vanish silently.
 */
class ThrowingEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embedBatch(array $texts): EmbeddingResult
    {
        throw new RuntimeException('embedding client misconfigured');
    }

    public function embed(string $text): EmbeddingResult
    {
        return $this->embedBatch([$text]);
    }

    public function name(): string
    {
        return 'throwing-test';
    }

    public function model(): string
    {
        return 'throwing-test-model';
    }

    public function dimensions(): int
    {
        return 8;
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
