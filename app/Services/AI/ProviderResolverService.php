<?php

namespace App\Services\AI;

use App\Exceptions\AI\AIProviderException;
use App\Models\AI\ProviderConfig;
use App\Services\Knowledge\Crawler\UrlSafetyGuard;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\Responses\OpenAIResponses;
use NeuronAI\Providers\OpenAILike;

class ProviderResolverService
{
    public function __construct(private readonly UrlSafetyGuard $urlSafety) {}

    /** @param array<string, mixed> $parameters */
    public function resolve(ProviderConfig $config, ?string $model = null, array $parameters = [], bool $forHealthCheck = false, ?int $timeoutSeconds = null): AIProviderInterface
    {
        if (! $forHealthCheck && ! $config->enabled) {
            throw new AIProviderException('provider_disabled', 'The selected provider is disabled.');
        }

        if (! $forHealthCheck && $config->circuit_open_until?->isFuture()) {
            throw new AIProviderException('circuit_open', 'The provider is temporarily paused after repeated failures.', true);
        }

        $definition = (array) config("ai.providers.{$config->provider}");
        if ($definition === []) {
            throw new AIProviderException('unsupported_provider', 'This provider type is not supported.');
        }

        $credentials = (array) ($config->credentials_encrypted ?? []);
        $key = (string) ($credentials['api_key'] ?? '');
        if (($definition['requires_api_key'] ?? false) && $key === '') {
            throw new AIProviderException('credential_missing', 'Add an API key before using this provider.');
        }

        $model = trim((string) ($model ?: $config->default_chat_model));
        if ($model === '') {
            throw new AIProviderException('model_missing', 'Choose a default chat model before using this provider.');
        }

        $timeout = $forHealthCheck
            ? (int) config('ai.runtime.provider_test_timeout_seconds', 15)
            : min((int) config('ai.runtime.maximum_timeout_seconds', 120), max(5, $timeoutSeconds ?? (int) config('ai.runtime.default_timeout_seconds', 45)));
        $http = new GuzzleHttpClient(timeout: $timeout, connectTimeout: min(10, $timeout));
        $parameters = $this->safeParameters($parameters + (array) ($config->configuration['parameters'] ?? []), $config->provider);

        return match ($config->provider) {
            'openai' => new OpenAIResponses($key, $model, $parameters, false, $http),
            'anthropic' => new Anthropic($key, $model, max_tokens: (int) ($parameters['max_tokens'] ?? 1200), parameters: $parameters, httpClient: $http),
            'gemini' => new Gemini($key, $model, $parameters, $http),
            'openai_compatible' => new OpenAILike($this->safeEndpoint($config), $key, $model, $parameters, false, $http),
            'ollama' => new Ollama($this->safeEndpoint($config), $model, $parameters, $http),
            default => throw new AIProviderException('unsupported_provider', 'This provider type is not supported.'),
        };
    }

    /** @param array<string, mixed> $parameters
     *  @return array<string, mixed>
     */
    private function safeParameters(array $parameters, string $provider): array
    {
        $values = [];
        foreach (['temperature', 'top_p', 'max_tokens'] as $name) {
            if (! array_key_exists($name, $parameters)) {
                continue;
            }
            $bounds = (array) config("ai.model_parameters.{$name}");
            $value = $name === 'max_tokens' ? (int) $parameters[$name] : (float) $parameters[$name];
            $values[$name] = max($bounds['min'], min($bounds['max'], $value));
        }
        $effort = in_array($parameters['reasoning_effort'] ?? null, ['low','medium','high'], true)
            ? $parameters['reasoning_effort'] : null;

        return match ($provider) {
            'openai' => array_filter([
                'temperature' => $effort ? null : ($values['temperature'] ?? null),
                'top_p' => $values['top_p'] ?? null,
                'max_output_tokens' => $values['max_tokens'] ?? null,
                'reasoning' => $effort ? ['effort' => $effort, 'summary' => 'auto'] : null,
            ], fn ($value) => $value !== null),
            'anthropic' => array_filter([
                'temperature' => $effort ? 1 : ($values['temperature'] ?? null),
                'top_p' => $effort ? null : ($values['top_p'] ?? null),
                'max_tokens' => $values['max_tokens'] ?? null,
                'thinking' => $effort ? ['type' => 'enabled', 'budget_tokens' => match ($effort) {'low' => 1024, 'medium' => 2048, default => 4096}] : null,
            ], fn ($value) => $value !== null),
            'gemini' => ['generationConfig' => array_filter([
                'temperature' => $values['temperature'] ?? null, 'topP' => $values['top_p'] ?? null,
                'maxOutputTokens' => $values['max_tokens'] ?? null,
                'thinkingConfig' => $effort ? ['thinkingBudget' => match ($effort) {'low' => 1024, 'medium' => 4096, default => 8192}] : null,
            ], fn ($value) => $value !== null)],
            'ollama' => array_filter([
                'options' => array_filter(['temperature' => $values['temperature'] ?? null, 'top_p' => $values['top_p'] ?? null, 'num_predict' => $values['max_tokens'] ?? null], fn ($value) => $value !== null),
                'think' => $effort ? true : null,
            ], fn ($value) => $value !== null),
            default => array_filter([
                'temperature' => $values['temperature'] ?? null, 'top_p' => $values['top_p'] ?? null,
                'max_tokens' => $values['max_tokens'] ?? null, 'reasoning_effort' => $effort,
            ], fn ($value) => $value !== null),
        };
    }

    private function safeEndpoint(ProviderConfig $config): string
    {
        $url = rtrim((string) $config->base_url, '/');
        if ($url === '') {
            throw new AIProviderException('endpoint_missing', 'Add a provider endpoint before using this configuration.');
        }

        if ($config->provider === 'ollama' && (bool) config('ai.providers.ollama.allow_private_endpoints', false) && app(AISettingsService::class)->current()['allow_private_provider_endpoints']) {
            if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                throw new AIProviderException('endpoint_invalid', 'The provider endpoint must use HTTP or HTTPS.');
            }
            return $url;
        }

        try {
            $this->urlSafety->assertSafe($url);
        } catch (\Throwable $exception) {
            throw new AIProviderException('endpoint_unsafe', 'The provider endpoint is not allowed by the workspace network policy.', false, $exception);
        }

        return $url;
    }
}
