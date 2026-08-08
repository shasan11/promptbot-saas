<?php

namespace App\Services\Knowledge\Data;

use App\Models\Knowledge\KnowledgeChunk;

/**
 * One scored chunk on its way through the retrieval pipeline. Scores are
 * attached progressively (semantic → keyword → fused → reranked) so the
 * debugger can show how a result reached its final position.
 */
final class RetrievalHit
{
    public function __construct(
        public readonly KnowledgeChunk $chunk,
        public float $semanticScore = 0.0,
        public float $keywordScore = 0.0,
        public float $fusedScore = 0.0,
        public float $finalScore = 0.0,
        public int $rank = 0,
        public bool $included = true,
        public ?string $exclusionReason = null,
    ) {}

    public function exclude(string $reason): self
    {
        $this->included = false;
        $this->exclusionReason = $reason;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'chunk_uuid' => $this->chunk->uuid,
            'content' => $this->chunk->content,
            'rank' => $this->rank,
            'semantic_score' => round($this->semanticScore, 5),
            'keyword_score' => round($this->keywordScore, 5),
            'score' => round($this->finalScore, 5),
            'token_count' => $this->chunk->token_count,
            'language' => $this->chunk->language,
            'citation' => $this->chunk->citation(),
            'included_in_context' => $this->included,
            'exclusion_reason' => $this->exclusionReason,
        ];
    }
}
