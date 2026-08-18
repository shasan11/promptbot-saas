<?php

namespace Tests\Support\Knowledge;

use App\Contracts\Knowledge\EmbeddingProviderInterface;
use App\Models\Knowledge\KnowledgeBase;
use App\Services\Knowledge\Embedding\EmbeddingProviderFactory;

/** Always returns one fixed provider, regardless of the base's configured driver. */
class FakeEmbeddingProviderFactory extends EmbeddingProviderFactory
{
    public function __construct(private readonly EmbeddingProviderInterface $provider) {}

    public function forKnowledgeBase(KnowledgeBase $base): EmbeddingProviderInterface
    {
        return $this->provider;
    }

    public function default(): EmbeddingProviderInterface
    {
        return $this->provider;
    }
}
