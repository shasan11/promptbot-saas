<?php

namespace App\Services\Knowledge\Embedding;

use App\Contracts\Knowledge\EmbeddingProviderInterface;
use App\Models\Knowledge\KnowledgeBase;
use InvalidArgumentException;

/**
 * Resolves the embedding provider a given knowledge base was indexed with.
 *
 * Resolution is per-base, not global: a workspace may be mid-migration between
 * models, and querying a base with a provider other than the one that produced
 * its vectors yields silently meaningless similarity scores.
 */
class EmbeddingProviderFactory
{
    /** @var array<string, EmbeddingProviderInterface> */
    private array $resolved = [];

    public function forKnowledgeBase(KnowledgeBase $base): EmbeddingProviderInterface
    {
        return $this->make($base->embedding_provider, [
            'model' => $base->embedding_model,
            'dimensions' => $base->embedding_dimensions,
        ]);
    }

    public function default(): EmbeddingProviderInterface
    {
        return $this->make((string) config('knowledge.embeddings.default_provider'));
    }

    /** @param  array<string, mixed>  $overrides */
    public function make(string $provider, array $overrides = []): EmbeddingProviderInterface
    {
        $config = config("knowledge.embeddings.providers.{$provider}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown knowledge embedding provider [{$provider}].");
        }

        $config = array_merge($config, array_filter($overrides, fn ($value) => $value !== null));
        $cacheKey = $provider.':'.($config['model'] ?? '').':'.($config['dimensions'] ?? '');

        return $this->resolved[$cacheKey] ??= match ($config['driver']) {
            'local' => new LocalHashEmbeddingProvider((int) $config['dimensions'], (string) $config['model']),
            'openai' => new OpenAiEmbeddingProvider($config),
            default => throw new InvalidArgumentException("Unsupported embedding driver [{$config['driver']}]."),
        };
    }

    /**
     * Providers offered in the UI, with the metadata the settings screen needs
     * to explain the trade-off between them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogue(): array
    {
        $options = [];

        foreach ((array) config('knowledge.embeddings.providers') as $key => $config) {
            $options[] = [
                'key' => $key,
                'model' => $config['model'],
                'dimensions' => $config['dimensions'],
                'cost_per_million_tokens' => $config['cost_per_million_tokens'] ?? 0,
                'configured' => $config['driver'] === 'local' || filled($config['api_key'] ?? null),
                'label' => match ($key) {
                    'local' => 'Built-in (offline)',
                    'openai' => 'OpenAI',
                    default => ucfirst((string) $key),
                },
                'description' => match ($key) {
                    'local' => 'Works with no API key or internet access. Matches wording well but does not understand '
                        .'that differently-phrased questions mean the same thing. Best for trialling the module.',
                    'openai' => 'Full semantic search. Finds answers phrased differently from the question. '
                        .'Requires an API key and bills per token embedded.',
                    default => '',
                },
            ];
        }

        return $options;
    }
}
