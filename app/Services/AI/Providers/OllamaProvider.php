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
use App\Services\AI\Exceptions\AIProviderRequestFailedException;
use App\Services\AI\Exceptions\AIProviderTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** No API key by default — Ollama is typically a local/self-hosted install. */
class OllamaProvider implements AIProviderDriverInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey = null,
        private readonly array $extraHeaders = [],
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function chat(ChatRequest $request, AiModel $model): ChatResult
    {
        $startedAt = hrtime(true);

        $response = $this->send(fn (PendingRequest $http) => $http->post('/api/chat', [
            'model' => $model->model_key,
            'messages' => array_map(
                fn ($message) => ['role' => $message->role, 'content' => $message->content],
                $request->messages
            ),
            'stream' => false,
        ]));

        if ($response->failed()) {
            throw $this->mapHttpFailure($response, $model->model_key);
        }

        $body = $response->json();

        return new ChatResult(
            content: (string) data_get($body, 'message.content', ''),
            provider: $this->driverKey(),
            model: $model->model_key,
            promptTokens: (int) data_get($body, 'prompt_eval_count', 0),
            completionTokens: (int) data_get($body, 'eval_count', 0),
            totalTokens: (int) data_get($body, 'prompt_eval_count', 0) + (int) data_get($body, 'eval_count', 0),
            estimatedCost: 0.0,
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            finishReason: data_get($body, 'done_reason'),
        );
    }

    public function embed(EmbedRequest $request, AiModel $model): EmbedResult
    {
        $startedAt = hrtime(true);
        $vectors = [];
        $dimensions = 0;

        foreach ($request->texts as $text) {
            $response = $this->send(fn (PendingRequest $http) => $http->post('/api/embeddings', [
                'model' => $model->model_key,
                'prompt' => $text,
            ]));

            if ($response->failed()) {
                throw $this->mapHttpFailure($response, $model->model_key);
            }

            $vector = array_map('floatval', data_get($response->json(), 'embedding', []));
            $vectors[] = $vector;
            $dimensions = count($vector);
        }

        return new EmbedResult(
            vectors: $vectors,
            provider: $this->driverKey(),
            model: $model->model_key,
            dimensions: $dimensions,
            estimatedCost: 0.0,
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
        );
    }

    public function testConnection(): TestConnectionResult
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->send(fn (PendingRequest $http) => $http->get('/api/tags'));
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
        return 'ollama';
    }

    private function send(callable $callback): Response
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

    private function mapHttpFailure(Response $response, string $model): AIException
    {
        $status = $response->status();

        return match (true) {
            $status === 404 => AIModelNotAvailableException::make($this->driverKey(), $model),
            in_array($status, [408, 504, 522, 524], true) => AIProviderTimeoutException::make($this->driverKey()),
            default => AIProviderRequestFailedException::make($this->driverKey()),
        };
    }
}
