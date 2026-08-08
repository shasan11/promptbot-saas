<?php

namespace App\Contracts\Knowledge;

use App\Services\Knowledge\Data\EmbeddingResult;

interface EmbeddingProviderInterface
{
    /**
     * Embeds a batch of texts. Implementations MUST return vectors in the same
     * order as the input and MUST throw rather than return a short array — a
     * silently misaligned batch would attach every chunk's vector to the wrong
     * chunk, which produces plausible-looking but completely wrong retrieval.
     *
     * @param  array<int, string>  $texts
     *
     * @throws \App\Exceptions\Knowledge\EmbeddingException
     */
    public function embedBatch(array $texts): EmbeddingResult;

    /** Convenience for a single text (query embedding, mostly). */
    public function embed(string $text): EmbeddingResult;

    public function name(): string;

    public function model(): string;

    /** Vectors of differing width are not comparable; the base pins this value. */
    public function dimensions(): int;

    /** Largest batch the provider accepts in one call. */
    public function maxBatchSize(): int;

    public function estimateCost(int $tokens): float;
}
