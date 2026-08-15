<?php

namespace App\Services\Knowledge\Embedding;

use App\Contracts\Knowledge\EmbeddingProviderInterface;
use App\Exceptions\Knowledge\EmbeddingException;
use App\Models\AI\ProviderConfig;
use App\Services\Knowledge\Data\EmbeddingResult;
use App\Services\Knowledge\Support\TokenEstimator;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\RAG\Embeddings\GeminiEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\OllamaEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\OpenAIEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\OpenAILikeEmbeddings;
use Throwable;
use App\Services\Knowledge\Crawler\UrlSafetyGuard;

class NeuronEmbeddingProviderAdapter implements EmbeddingProviderInterface
{
    private readonly string $resolvedModel;

    public function __construct(private readonly ProviderConfig $config, string $model, private readonly int $dimensions, private readonly UrlSafetyGuard $urlSafety)
    {
        $this->resolvedModel = $model === 'tenant-provider-default' ? (string) $config->default_embedding_model : $model;
        if ($this->resolvedModel === '') throw EmbeddingException::providerFailed('tenant_ai', 'No embedding model is configured.');
    }

    public function embedBatch(array $texts): EmbeddingResult
    {
        $started = hrtime(true); $vectors = []; $provider = $this->neuron();
        try {
            foreach ($texts as $text) {
                $vector = array_map('floatval', $provider->embedText($text));
                if (count($vector) !== $this->dimensions) throw EmbeddingException::dimensionMismatch($this->dimensions, count($vector));
                $vectors[] = $vector;
            }
        } catch (EmbeddingException $exception) { throw $exception; }
        catch (Throwable $exception) { throw EmbeddingException::providerFailed($this->config->provider, 'Provider request failed safely.', $exception); }
        return new EmbeddingResult($vectors, $this->name(), $this->resolvedModel, $this->dimensions, array_sum(array_map(fn ($text) => TokenEstimator::estimate($text), $texts)), 0.0, (int) round((hrtime(true) - $started) / 1_000_000));
    }

    public function embed(string $text): EmbeddingResult { return $this->embedBatch([$text]); }
    public function name(): string { return 'tenant_ai'; }
    public function model(): string { return $this->resolvedModel; }
    public function dimensions(): int { return $this->dimensions; }
    public function maxBatchSize(): int { return 100; }
    public function estimateCost(int $tokens): float { return 0.0; }

    private function neuron(): \NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface
    {
        $key = (string) ($this->config->credentials_encrypted['api_key'] ?? '');
        $client = new GuzzleHttpClient(timeout: (int) config('ai.runtime.default_timeout_seconds', 45), connectTimeout: 10);
        if (in_array($this->config->provider, ['openai_compatible','ollama'], true)) {
            $privateOllama = $this->config->provider === 'ollama' && config('ai.providers.ollama.allow_private_endpoints') && app(\App\Services\AI\AISettingsService::class)->current()['allow_private_provider_endpoints'];
            if (! $privateOllama) $this->urlSafety->assertSafe((string) $this->config->base_url);
        }
        return match ($this->config->provider) {
            'openai' => new OpenAIEmbeddingsProvider($key, $this->resolvedModel, $this->dimensions, $client),
            'gemini' => new GeminiEmbeddingsProvider($key, $this->resolvedModel, ['outputDimensionality' => $this->dimensions], $client),
            'openai_compatible' => new OpenAILikeEmbeddings(rtrim((string) $this->config->base_url, '/'), $key, $this->resolvedModel, $this->dimensions, $client),
            'ollama' => new OllamaEmbeddingsProvider($this->resolvedModel, rtrim((string) $this->config->base_url, '/'), [], $client),
            default => throw EmbeddingException::providerFailed($this->config->provider, 'This provider does not support embeddings.'),
        };
    }
}
