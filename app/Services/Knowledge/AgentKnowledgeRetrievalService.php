<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\RetrievalMode;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeRetrievalLog;
use App\Services\Knowledge\Data\RetrievalQuery;
use App\Services\Knowledge\Data\RetrievalOutcome;

class AgentKnowledgeRetrievalService
{
    public function __construct(
        private readonly KnowledgePermissionService $permissions,
        private readonly KnowledgeRetrievalService $retrieval,
    ) {}

    public function retrieve(string $agentKey, string $question, array $options = []): RetrievalOutcome
    {
        $scope = $this->permissions->allowedForAgent($agentKey);
        $baseIds = $scope['knowledge_base_ids'];
        $primary = $baseIds ? KnowledgeBase::find($baseIds[0]) : null;

        $query = new RetrievalQuery(
            query: $question,
            knowledgeBaseIds: $baseIds,
            allowedCollectionIds: $scope['collection_ids'],
            sourceIds: array_map('intval', $options['source_ids'] ?? []),
            tags: $options['tags'] ?? [],
            language: $options['language'] ?? null,
            mode: RetrievalMode::tryFrom((string) ($options['mode'] ?? '')) ?? ($primary?->retrieval_mode ?? RetrievalMode::Hybrid),
            topK: (int) ($options['top_k'] ?? ($primary?->top_k ?? 5)),
            candidatePool: (int) ($options['candidate_pool'] ?? ($primary?->candidate_pool ?? 20)),
            similarityThreshold: (float) ($options['similarity_threshold'] ?? ($primary?->similarity_threshold ?? 0.7)),
            rerank: (bool) ($options['rerank'] ?? ($primary?->reranking_enabled ?? true)),
            preferRecent: (bool) ($options['prefer_recent'] ?? ($primary?->prefer_recent_content ?? false)),
            excludeExpired: (bool) ($options['exclude_expired'] ?? ($primary?->exclude_expired_content ?? true)),
            maxContextTokens: (int) ($options['max_context_tokens'] ?? ($primary?->max_context_tokens ?? 8000)),
            channel: KnowledgeRetrievalLog::CHANNEL_AGENT,
            agentKey: $agentKey,
        );

        return $this->retrieval->retrieve($query, $primary);
    }
}
