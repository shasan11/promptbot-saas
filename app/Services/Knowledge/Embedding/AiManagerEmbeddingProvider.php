<?php

namespace App\Services\Knowledge\Embedding;

use App\Contracts\Knowledge\EmbeddingProviderInterface;
use App\Enums\AI\AIModelCapability;
use App\Enums\AI\AIPurpose;
use App\Exceptions\Knowledge\EmbeddingException;
use App\Models\AiModel;
use App\Services\AI\AIFeatureManager;
use App\Services\AI\AIManager;
use App\Services\AI\AIModelResolver;
use App\Services\AI\Data\EmbedRequest;
use App\Services\AI\Exceptions\AIException;
use App\Services\AI\Exceptions\AIProviderAuthenticationException;
use App\Services\AI\Exceptions\AIProviderRateLimitException;
use App\Services\Knowledge\Data\EmbeddingResult;

/**
 * Adapter between the AI & LLM module and Knowledge's own embedding
 * abstraction. This is the only file that imports both modules — the AI
 * module has zero compile-time dependency on Knowledge, and vice versa.
 *
 * model()/dimensions() must be answerable without making a provider call
 * (KnowledgeBaseService reads them at base-creation time), so the assigned
 * model's own database row — not a live API response — is the source of
 * truth for those two values.
 */
class AiManagerEmbeddingProvider implements EmbeddingProviderInterface
{
    private ?AiModel $resolvedModel = null;

    public function __construct(
        private readonly AIManager $ai,
        private readonly AIFeatureManager $features,
        private readonly AIModelResolver $modelResolver,
    ) {}

    public function embedBatch(array $texts): EmbeddingResult
    {
        try {
            $this->features->assertEnabled('knowledge_embeddings');
            $result = $this->ai->forPurpose(AIPurpose::KnowledgeEmbedding)->embed(new EmbedRequest($texts));
        } catch (AIProviderRateLimitException $exception) {
            throw EmbeddingException::rateLimited($this->name(), $exception);
        } catch (AIProviderAuthenticationException $exception) {
            throw EmbeddingException::unauthorised($this->name(), $exception);
        } catch (AIException $exception) {
            throw EmbeddingException::providerFailed($this->name(), $exception->operatorMessage(), $exception);
        }

        if (count($result->vectors) !== count($texts)) {
            throw EmbeddingException::misalignedBatch($this->name(), count($texts), count($result->vectors));
        }

        return new EmbeddingResult(
            vectors: $result->vectors,
            provider: $this->name(),
            model: $result->model,
            dimensions: $result->dimensions,
            tokensUsed: $result->tokensUsed,
            estimatedCost: $result->estimatedCost,
            latencyMs: $result->latencyMs,
        );
    }

    public function embed(string $text): EmbeddingResult
    {
        return $this->embedBatch([$text]);
    }

    public function name(): string
    {
        return 'ai_manager';
    }

    public function model(): string
    {
        return $this->resolveModel()?->model_key ?? 'unconfigured';
    }

    public function dimensions(): int
    {
        return (int) ($this->resolveModel()?->embedding_dimensions ?? 0);
    }

    public function maxBatchSize(): int
    {
        return 100;
    }

    public function estimateCost(int $tokens): float
    {
        $model = $this->resolveModel();

        if (! $model || (float) $model->input_cost_per_million_tokens <= 0) {
            return 0.0;
        }

        return ($tokens / 1_000_000) * (float) $model->input_cost_per_million_tokens;
    }

    private function resolveModel(): ?AiModel
    {
        if ($this->resolvedModel) {
            return $this->resolvedModel;
        }

        try {
            return $this->resolvedModel = $this->modelResolver
                ->chainFor(AIPurpose::KnowledgeEmbedding, AIModelCapability::Embedding)
                ->first();
        } catch (AIException) {
            return null;
        }
    }
}
