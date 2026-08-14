<?php

namespace App\Services\AI;

use App\Enums\AI\AIModelCapability;
use App\Enums\AI\AIPurpose;
use App\Models\AiModel;
use App\Services\AI\Contracts\AIProviderDriverInterface;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Data\ChatResult;
use App\Services\AI\Data\EmbedRequest;
use App\Services\AI\Data\EmbedResult;
use App\Services\AI\Exceptions\AIConfigurationMissingException;
use App\Services\AI\Exceptions\AIException;

class AIManager
{
    private ?AIPurpose $currentPurpose = null;

    public function __construct(
        private readonly AIConfigResolver $config,
        private readonly AIProviderRegistry $registry,
        private readonly AIModelResolver $modelResolver,
        private readonly AIUsageTracker $usage,
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    /** Returns a purpose-bound clone; does not mutate the shared singleton. */
    public function forPurpose(AIPurpose $purpose): self
    {
        $clone = clone $this;
        $clone->currentPurpose = $purpose;

        return $clone;
    }

    public function chat(ChatRequest $request): ChatResult
    {
        $purpose = $this->currentPurpose ?? $request->purpose;

        return $this->attempt(
            $purpose,
            AIModelCapability::Chat,
            fn (AIProviderDriverInterface $driver, AiModel $model) => $driver->chat($request, $model),
        );
    }

    public function embed(EmbedRequest $request): EmbedResult
    {
        $purpose = $this->currentPurpose ?? $request->purpose;

        return $this->attempt(
            $purpose,
            AIModelCapability::Embedding,
            fn (AIProviderDriverInterface $driver, AiModel $model) => $driver->embed($request, $model),
        );
    }

    public function provider(string $slug): AIProviderDriverInterface
    {
        return $this->registry->driverFor($this->registry->providerBySlug($slug));
    }

    /**
     * @template T
     *
     * @param  callable(AIProviderDriverInterface, AiModel): T  $call
     * @return T
     */
    private function attempt(AIPurpose $purpose, AIModelCapability $capability, callable $call): mixed
    {
        if (! $this->config->isEnabled()) {
            throw AIConfigurationMissingException::masterDisabled();
        }

        $chain = $this->modelResolver->chainFor($purpose, $capability);
        $attempted = [];
        $lastException = null;

        foreach ($chain as $model) {
            $provider = $model->provider;

            if (! $provider || in_array($model->id, $attempted, true)) {
                continue;
            }

            $attempted[] = $model->id;

            if ($this->circuitBreaker->isOpen($provider->id)) {
                continue;
            }

            $startedAt = hrtime(true);

            try {
                $driver = $this->registry->driverFor($provider);
                $result = $call($driver, $model);

                [$promptTokens, $completionTokens] = match (true) {
                    $result instanceof ChatResult => [$result->promptTokens, $result->completionTokens],
                    $result instanceof EmbedResult => [$result->tokensUsed, 0],
                    default => [0, 0],
                };

                $this->usage->record(
                    purpose: $purpose,
                    capability: $capability,
                    provider: $provider,
                    model: $model,
                    status: 'success',
                    promptTokens: $promptTokens,
                    completionTokens: $completionTokens,
                    estimatedCost: $result->estimatedCost,
                    latencyMs: $result->latencyMs,
                );
                $this->circuitBreaker->recordSuccess($provider->id);

                return $result;
            } catch (AIException $exception) {
                $lastException = $exception;

                $this->usage->record(
                    purpose: $purpose,
                    capability: $capability,
                    provider: $provider,
                    model: $model,
                    status: 'failed',
                    errorCode: $exception->errorCode(),
                    latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
                );
                $this->circuitBreaker->recordFailure($provider->id);

                if (! $this->config->isFallbackEnabled()) {
                    throw $exception;
                }
            }
        }

        throw $lastException ?? AIConfigurationMissingException::noModelsConfigured($purpose->value);
    }
}
