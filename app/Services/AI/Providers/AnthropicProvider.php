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

class AnthropicProvider implements AIProviderDriverInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly array $extraHeaders = [],
        private readonly int $timeoutSeconds = 30,
        private readonly string $anthropicVersion = '2023-06-01',
    ) {}

    public function chat(ChatRequest $request, AiModel $model): ChatResult
    {
        $startedAt = hrtime(true);

        $system = null;
        $messages = [];

        foreach ($request->messages as $message) {
            if ($message->role === 'system') {
                $system = $message->content;

                continue;
            }

            $messages[] = ['role' => $message->role, 'content' => $message->content];
        }

        $payload = array_filter([
            'model' => $model->model_key,
            'max_tokens' => $request->maxTokens ?? 1024,
            'messages' => $messages,
            'system' => $system,
            'temperature' => $request->temperature,
        ], fn ($value) => $value !== null);

        $response = $this->send(fn (PendingRequest $http) => $http->post('/v1/messages', $payload));

        if ($response->failed()) {
            throw $this->mapHttpFailure($response, $model->model_key);
        }

        $body = $response->json();

        return new ChatResult(
            content: (string) data_get($body, 'content.0.text', ''),
            provider: $this->driverKey(),
            model: $model->model_key,
            promptTokens: (int) data_get($body, 'usage.input_tokens', 0),
            completionTokens: (int) data_get($body, 'usage.output_tokens', 0),
            totalTokens: (int) data_get($body, 'usage.input_tokens', 0) + (int) data_get($body, 'usage.output_tokens', 0),
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            finishReason: data_get($body, 'stop_reason'),
        );
    }

    public function embed(EmbedRequest $request, AiModel $model): EmbedResult
    {
        throw AIModelNotAvailableException::make($this->driverKey(), $model->model_key);
    }

    public function testConnection(): TestConnectionResult
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->send(fn (PendingRequest $http) => $http->post('/v1/messages', [
                'model' => 'claude-3-5-haiku-latest',
                'max_tokens' => 1,
                'messages' => [['role' => 'user', 'content' => 'ping']],
            ]));
        } catch (AIException $exception) {
            return TestConnectionResult::failure($exception->operatorMessage());
        }

        $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        // A 404 here means the probe model name is stale, not that the key is bad —
        // any non-auth/non-rate-limit response confirms the key itself was accepted.
        if ($response->status() === 401 || $response->status() === 403) {
            return TestConnectionResult::failure(AIProviderAuthenticationException::make($this->driverKey())->operatorMessage(), $latencyMs);
        }

        if ($response->status() === 429) {
            return TestConnectionResult::failure(AIProviderRateLimitException::make($this->driverKey())->operatorMessage(), $latencyMs);
        }

        return TestConnectionResult::success('Connection successful.', $latencyMs);
    }

    public function driverKey(): string
    {
        return 'anthropic';
    }

    private function send(callable $callback): Response
    {
        $http = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeoutSeconds)
            ->withHeaders(array_merge([
                'anthropic-version' => $this->anthropicVersion,
                'content-type' => 'application/json',
            ], $this->apiKey ? ['x-api-key' => $this->apiKey] : [], $this->extraHeaders));

        try {
            return $callback($http);
        } catch (ConnectionException $exception) {
            throw AIProviderTimeoutException::make($this->driverKey(), $exception);
        }
    }

    private function mapHttpFailure(Response $response, string $model): AIException
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
