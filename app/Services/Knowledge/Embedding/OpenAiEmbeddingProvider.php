<?php

namespace App\Services\Knowledge\Embedding;

use App\Contracts\Knowledge\EmbeddingProviderInterface;
use App\Exceptions\Knowledge\EmbeddingException;
use App\Services\Knowledge\Data\EmbeddingResult;
use App\Services\Knowledge\Support\TokenEstimator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenAI-compatible embeddings. `base_url` is configurable, so this also drives
 * Azure OpenAI and the many self-hosted servers that speak the same wire format.
 */
class OpenAiEmbeddingProvider implements EmbeddingProviderInterface
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private readonly array $config) {}

    public function embedBatch(array $texts): EmbeddingResult
    {
        if (! $texts) {
            return new EmbeddingResult([], $this->name(), $this->model(), $this->dimensions());
        }

        if (blank($this->config['api_key'] ?? null)) {
            throw EmbeddingException::unauthorised($this->name());
        }

        $startedAt = hrtime(true);

        try {
            $response = Http::withToken($this->config['api_key'])
                ->timeout((int) ($this->config['timeout'] ?? 60))
                ->asJson()
                ->post(rtrim((string) $this->config['base_url'], '/').'/embeddings', [
                    'model' => $this->model(),
                    'input' => array_values($texts),
                    'dimensions' => $this->dimensions(),
                ]);
        } catch (ConnectionException $e) {
            throw EmbeddingException::providerFailed($this->name(), 'connection failed', $e);
        } catch (Throwable $e) {
            throw EmbeddingException::providerFailed($this->name(), $e->getMessage(), $e);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw EmbeddingException::unauthorised($this->name());
        }

        if ($response->status() === 429) {
            throw EmbeddingException::rateLimited($this->name());
        }

        if ($response->failed()) {
            throw EmbeddingException::providerFailed(
                $this->name(),
                'HTTP '.$response->status().': '.$response->json('error.message', $response->body())
            );
        }

        $payload = $response->json();
        $data = $payload['data'] ?? [];

        if (count($data) !== count($texts)) {
            throw EmbeddingException::misalignedBatch($this->name(), count($texts), count($data));
        }

        // The API documents that results carry an `index`, but does not
        // guarantee array order matches it. Sorting explicitly is the
        // difference between correct vectors and confidently wrong ones.
        usort($data, fn (array $a, array $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        $vectors = [];

        foreach ($data as $item) {
            $vector = $item['embedding'] ?? null;

            if (! is_array($vector) || count($vector) !== $this->dimensions()) {
                throw EmbeddingException::dimensionMismatch($this->dimensions(), is_array($vector) ? count($vector) : 0);
            }

            $vectors[] = array_map('floatval', $vector);
        }

        $tokens = (int) ($payload['usage']['total_tokens']
            ?? array_sum(array_map(fn (string $t) => TokenEstimator::estimate($t), $texts)));

        return new EmbeddingResult(
            vectors: $vectors,
            provider: $this->name(),
            model: $this->model(),
            dimensions: $this->dimensions(),
            tokensUsed: $tokens,
            estimatedCost: $this->estimateCost($tokens),
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
        );
    }

    public function embed(string $text): EmbeddingResult
    {
        return $this->embedBatch([$text]);
    }

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) $this->config['model'];
    }

    public function dimensions(): int
    {
        return (int) $this->config['dimensions'];
    }

    public function maxBatchSize(): int
    {
        return 96;
    }

    public function estimateCost(int $tokens): float
    {
        return ($tokens / 1_000_000) * (float) ($this->config['cost_per_million_tokens'] ?? 0);
    }
}
