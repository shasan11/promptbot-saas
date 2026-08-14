<?php

namespace App\Services\AI\Providers;

use App\Models\AiModel;
use App\Services\AI\Contracts\AIProviderDriverInterface;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Data\ChatResult;
use App\Services\AI\Data\EmbedRequest;
use App\Services\AI\Data\EmbedResult;
use App\Services\AI\Data\TestConnectionResult;
use App\Services\AI\Exceptions\AIException;
use App\Services\AI\Exceptions\AIModelNotAvailableException;
use App\Services\AI\Exceptions\AIProviderAuthenticationException;
use App\Services\AI\Exceptions\AIProviderRateLimitException;
use App\Services\AI\Exceptions\AIProviderRequestFailedException;
use App\Services\AI\Exceptions\AIProviderTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared HTTP client for every provider that speaks the OpenAI
 * chat-completions/embeddings/models wire format (OpenAI itself, OpenRouter,
 * Groq, Mistral, and a fully custom OpenAI-compatible endpoint). Only the
 * base URL, auth header, and optional extra headers differ between them.
 */
abstract class AbstractOpenAiCompatibleProvider implements AIProviderDriverInterface
{
    public function __construct(
        protected readonly string $baseUrl,
        protected readonly ?string $apiKey,
        protected readonly array $extraHeaders = [],
        protected readonly int $timeoutSeconds = 30,
        protected readonly int $maxRetries = 2,
    ) {}

    public function chat(ChatRequest $request, AiModel $model): ChatResult
    {
        $startedAt = hrtime(true);

        $payload = [
            'model' => $model->model_key,
            'messages' => array_map(
                fn ($message) => ['role' => $message->role, 'content' => $message->content],
                $request->messages
            ),
        ];

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->maxTokens !== null) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        if ($request->jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->send(fn (PendingRequest $http) => $http->post('/chat/completions', $payload));

        if ($response->failed()) {
            throw $this->mapHttpFailure($response, $model->model_key);
        }

        $body = $response->json();
        $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        return new ChatResult(
            content: (string) data_get($body, 'choices.0.message.content', ''),
            provider: $this->driverKey(),
            model: $model->model_key,
            promptTokens: (int) data_get($body, 'usage.prompt_tokens', 0),
            completionTokens: (int) data_get($body, 'usage.completion_tokens', 0),
            totalTokens: (int) data_get($body, 'usage.total_tokens', 0),
            latencyMs: $latencyMs,
            finishReason: data_get($body, 'choices.0.finish_reason'),
        );
    }

    public function embed(EmbedRequest $request, AiModel $model): EmbedResult
    {
        $startedAt = hrtime(true);

        $response = $this->send(fn (PendingRequest $http) => $http->post('/embeddings', [
            'model' => $model->model_key,
            'input' => $request->texts,
        ]));

        if ($response->failed()) {
            throw $this->mapHttpFailure($response, $model->model_key);
        }

        $body = $response->json();
        $vectors = collect(data_get($body, 'data', []))
            ->sortBy('index')
            ->pluck('embedding')
            ->map(fn ($vector) => array_map('floatval', $vector))
            ->values()
            ->all();

        return new EmbedResult(
            vectors: $vectors,
            provider: $this->driverKey(),
            model: $model->model_key,
            dimensions: count($vectors[0] ?? []),
            tokensUsed: (int) data_get($body, 'usage.total_tokens', data_get($body, 'usage.prompt_tokens', 0)),
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
        );
    }

    public function testConnection(): TestConnectionResult
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->send(fn (PendingRequest $http) => $http->get('/models'));
        } catch (AIException $exception) {
            return TestConnectionResult::failure($exception->operatorMessage());
        }

        $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            return TestConnectionResult::failure($this->mapHttpFailure($response, 'test-connection')->operatorMessage(), $latencyMs);
        }

        return TestConnectionResult::success('Connection successful.', $latencyMs);
    }

    protected function send(callable $callback): Response
    {
        $http = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->withHeaders($this->extraHeaders);

        if ($this->apiKey) {
            $http = $http->withToken($this->apiKey);
        }

        try {
            return $callback($http);
        } catch (ConnectionException $exception) {
            throw AIProviderTimeoutException::make($this->driverKey(), $exception);
        }
    }

    protected function mapHttpFailure(Response $response, string $model): AIException
    {
        $status = $response->status();

        return match (true) {
            $status === 401 || $status === 403 => AIProviderAuthenticationException::make($this->driverKey()),
            $status === 429 => AIProviderRateLimitException::make($this->driverKey()),
            $status === 404 => AIModelNotAvailableException::make($this->driverKey(), $model),
            in_array($status, [408, 504, 522, 524], true) => AIProviderTimeoutException::make($this->driverKey()),
            default => AIProviderRequestFailedException::make($this->driverKey()),
        };
    }
}
