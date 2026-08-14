<?php

namespace App\Services\Knowledge\Embedding;

use App\Contracts\Knowledge\EmbeddingProviderInterface;
use App\Models\Knowledge\KnowledgeBase;
use App\Services\AI\AIFeatureManager;
use App\Services\AI\AIManager;
use App\Services\AI\AIModelResolver;
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

    public function __construct(private readonly AIFeatureManager $features) {}

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
            'ai_manager' => new AiManagerEmbeddingProvider(app(AIManager::class), app(AIFeatureManager::class), app(AIModelResolver::class)),
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
                'configured' => match ($config['driver']) {
                    'local' => true,
                    'ai_manager' => $this->features->isEnabled('knowledge_embeddings'),
                    default => false,
                },
                'label' => match ($key) {
                    'local' => 'Built-in (offline)',
                    'ai_manager' => 'AI Provider (Superadmin-configured)',
                    default => ucfirst((string) $key),
                },
                'description' => match ($key) {
                    'local' => 'Deterministic offline token matching. Requires no API key, external service, or usage billing.',
                    'ai_manager' => 'Uses the LLM provider configured by your platform in Superadmin → AI & LLM. Requires the platform owner to enable AI and the Knowledge Embeddings feature toggle.',
                    default => '',
                },
            ];
        }

        return $options;
    }
}
