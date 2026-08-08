<?php

namespace App\Services\Knowledge\Data;

/**
 * A prospective chunk produced by the chunker, before it is persisted or
 * embedded. Carries the citation metadata that will be frozen onto the row.
 */
final class ChunkCandidate
{
    /** @param  array<string, mixed>  $metadata */
    public function __construct(
        public readonly int $index,
        public readonly string $content,
        public readonly array $metadata = [],
        public readonly ?int $tokenCount = null,
    ) {}

    public function hash(): string
    {
        return hash('sha256', $this->normalisedContent());
    }

    /**
     * Whitespace-normalised text, used for hashing only. Two chunks that differ
     * solely in line wrapping are the same knowledge and should not be
     * re-embedded on the next crawl.
     */
    public function normalisedContent(): string
    {
        return trim(preg_replace('/\s+/u', ' ', $this->content) ?? '');
    }

    public function characterCount(): int
    {
        return mb_strlen($this->content);
    }
}
