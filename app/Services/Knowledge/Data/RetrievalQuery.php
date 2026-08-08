<?php

namespace App\Services\Knowledge\Data;

use App\Enums\Knowledge\RetrievalMode;

/**
 * Everything a retrieval request needs, including the resolved permission
 * allow-lists.
 *
 * The allow-lists are constructor arguments rather than something the retrieval
 * service derives on its own. That is deliberate: it makes it impossible to
 * build a query object without having decided who is asking, and it means the
 * unit tests for scoring can supply explicit scopes instead of a logged-in user.
 *
 * `allowedCollectionIds === null` means "no collection restriction"; an empty
 * array means "restricted, and nothing is permitted" — those are opposites and
 * must never be conflated.
 */
final class RetrievalQuery
{
    /**
     * @param  array<int, int>  $knowledgeBaseIds
     * @param  array<int, int>|null  $allowedCollectionIds
     * @param  array<int, int>  $sourceIds
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly string $query,
        public readonly array $knowledgeBaseIds,
        public readonly ?array $allowedCollectionIds = null,
        public readonly array $sourceIds = [],
        public readonly array $tags = [],
        public readonly ?string $language = null,
        public readonly RetrievalMode $mode = RetrievalMode::Hybrid,
        public readonly int $topK = 5,
        public readonly int $candidatePool = 20,
        public readonly float $similarityThreshold = 0.7,
        public readonly bool $rerank = true,
        public readonly bool $preferRecent = false,
        public readonly bool $excludeExpired = true,
        public readonly int $maxContextTokens = 8000,
        public readonly string $channel = 'playground',
        public readonly ?string $agentKey = null,
        public readonly bool $debug = false,
    ) {}

    /** True when the actor is permitted nothing at all — short-circuit, do not search. */
    public function isEmptyScope(): bool
    {
        return $this->knowledgeBaseIds === []
            || $this->allowedCollectionIds === [];
    }

    public function normalisedQuery(): string
    {
        return trim(preg_replace('/\s+/u', ' ', $this->query) ?? '');
    }
}
