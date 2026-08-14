<?php

namespace App\Services\AI;

use App\Enums\AI\AIModelCapability;
use App\Enums\AI\AIProviderDriver;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\AI\Contracts\AIProviderDriverInterface;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\CustomOpenAiCompatibleProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\MistralProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenAiProvider;
use App\Services\AI\Providers\OpenRouterProvider;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class AIProviderRegistry
{
    public function __construct(private readonly AIConfigResolver $config) {}

    /** @return Collection<int, AiProvider> */
    public function enabledProviders(): Collection
    {
        return AiProvider::query()->where('is_enabled', true)->orderBy('priority')->get();
    }

    public function driverFor(AiProvider $provider): AIProviderDriverInterface
    {
        $baseUrl = $provider->base_url ?: $provider->driver->defaultBaseUrl();
        $timeout = $this->config->timeoutSeconds($provider);
        $retries = $this->config->maxRetries($provider);
        $headers = $provider->extra_headers ?? [];

        return match ($provider->driver) {
            AIProviderDriver::OpenAI => new OpenAiProvider($baseUrl, $provider->api_key, $headers, $timeout, $retries),
            AIProviderDriver::OpenRouter => new OpenRouterProvider($baseUrl, $provider->api_key, $headers, $timeout, $retries),
            AIProviderDriver::Groq => new GroqProvider($baseUrl, $provider->api_key, $headers, $timeout, $retries),
            AIProviderDriver::Mistral => new MistralProvider($baseUrl, $provider->api_key, $headers, $timeout, $retries),
            AIProviderDriver::Custom => new CustomOpenAiCompatibleProvider($baseUrl, $provider->api_key, $headers, $timeout, $retries),
            AIProviderDriver::Anthropic => new AnthropicProvider($baseUrl, $provider->api_key, $headers, $timeout, data_get($provider->metadata, 'anthropic_version', '2023-06-01')),
            AIProviderDriver::Gemini => new GeminiProvider($baseUrl, $provider->api_key, $headers, $timeout),
            AIProviderDriver::Ollama => new OllamaProvider($baseUrl, $provider->api_key, $headers, $timeout),
        };
    }

    public function resolveModel(string $modelKey, AIModelCapability $capability): ?AiModel
    {
        return AiModel::query()
            ->where('model_key', $modelKey)
            ->where('capability', $capability->value)
            ->first();
    }

    public function providerBySlug(string $slug): AiProvider
    {
        $provider = AiProvider::query()->where('slug', $slug)->first();

        if (! $provider) {
            throw new InvalidArgumentException("Unknown AI provider [{$slug}].");
        }

        return $provider;
    }
}
