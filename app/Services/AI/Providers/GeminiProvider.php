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

class GeminiProvider implements AIProviderDriverInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly array $extraHeaders = [],
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function chat(ChatRequest $request, AiModel $model): ChatResult
    {
        $startedAt = hrtime(true);

        $contents = [];
        $systemInstruction = null;

        foreach ($request->messages as $message) {
            if ($message->role === 'system') {
                $systemInstruction = ['parts' => [['text' => $message->content]]];

                continue;
            }

            $contents[] = [
                'role' => $message->role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message->content]],
            ];
        }

        $payload = array_filter([
            'contents' => $contents,
            'systemInstruction' => $systemInstruction,
            'generationConfig' => array_filter([
                'temperature' => $request->temperature,
                'maxOutputTokens' => $request->maxTokens,
            ], fn ($value) => $value !== null) ?: null,
        ], fn ($value) => $value !== null);

        $response = $this->send(fn (PendingRequest $http) => $http->post(
            "/v1beta/models/{$model->model_key}:generateContent",
            $payload
        ));

        if ($response->failed()) {
            throw $this->mapHttpFailure($response, $model->model_key);
        }

        $body = $response->json();

        return new ChatResult(
            content: (string) data_get($body, 'candidates.0.content.parts.0.text', ''),
            provider: $this->driverKey(),
            model: $model->model_key,
            promptTokens: (int) data_get($body, 'usageMetadata.promptTokenCount', 0),
            completionTokens: (int) data_get($body, 'usageMetadata.candidatesTokenCount', 0),
            totalTokens: (int) data_get($body, 'usageMetadata.totalTokenCount', 0),
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            finishReason: data_get($body, 'candidates.0.finishReason'),
        );
    }

    public function embed(EmbedRequest $request, AiModel $model): EmbedResult
    {
        $startedAt = hrtime(true);
        $vectors = [];
        $dimensions = 0;

        foreach ($request->texts as $text) {
            $response = $this->send(fn (PendingRequest $http) => $http->post(
                "/v1beta/models/{$model->model_key}:embedContent",
                ['content' => ['parts' => [['text' => $text]]]]
            ));

            if ($response->failed()) {
                throw $this->mapHttpFailure($response, $model->model_key);
            }

            $vector = array_map('floatval', data_get($response->json(), 'embedding.values', []));
            $vectors[] = $vector;
            $dimensions = count($vector);
        }

        return new EmbedResult(
            vectors: $vectors,
            provider: $this->driverKey(),
            model: $model->model_key,
            dimensions: $dimensions,
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
        );
    }

    public function testConnection(): TestConnectionResult
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->send(fn (PendingRequest $http) => $http->get('/v1beta/models'));
        } catch (AIException $exception) {
            return TestConnectionResult::failure($exception->operatorMessage());
        }

        $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            return TestConnectionResult::failure($this->mapHttpFailure($response, 'test-connection')->operatorMessage(), $latencyMs);
        }

        return TestConnectionResult::success('Connection successful.', $latencyMs);
    }

    public function driverKey(): string
    {
        return 'gemini';
    }

    private function send(callable $callback): Response
    {
        $http = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->withHeaders($this->extraHeaders)
            ->withOptions(['query' => ['key' => $this->apiKey]]);

        try {
            return $callback($http);
        } catch (ConnectionException $exception) {
            throw AIProviderTimeoutException::make($this->driverKey(), $exception);
        }
    }

    private function mapHttpFailure(Response $response, string $model): AIException
    {
        $status = $response->status();
        $googleStatus = (string) data_get($response->json(), 'error.status');

        return match (true) {
            $status === 401 || $status === 403 => AIProviderAuthenticationException::make($this->driverKey()),
            $status === 429 || $googleStatus === 'RESOURCE_EXHAUSTED' => AIProviderRateLimitException::make($this->driverKey()),
            $status === 404 => AIModelNotAvailableException::make($this->driverKey(), $model),
            in_array($status, [408, 504, 522, 524], true) => AIProviderTimeoutException::make($this->driverKey()),
            default => AIProviderRequestFailedException::make($this->driverKey()),
        };
    }
}
