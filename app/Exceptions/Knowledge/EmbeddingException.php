<?php

namespace App\Exceptions\Knowledge;

use App\Enums\Knowledge\FailureCategory;
use Throwable;

class EmbeddingException extends KnowledgeException
{
    public static function providerFailed(string $provider, string $detail, ?Throwable $previous = null): self
    {
        return new self(
            "Embedding provider [{$provider}] failed: {$detail}",
            FailureCategory::EmbeddingProviderError,
            'The embedding service rejected the request. Check the provider credentials in Knowledge settings, then retry.',
            $previous,
        );
    }

    public static function rateLimited(string $provider, ?Throwable $previous = null): self
    {
        return new self(
            "Embedding provider [{$provider}] rate-limited the request",
            FailureCategory::RateLimit,
            'The embedding service is rate-limiting us. Processing will retry automatically — no action needed.',
            $previous,
        );
    }

    public static function unauthorised(string $provider, ?Throwable $previous = null): self
    {
        return new self(
            "Embedding provider [{$provider}] rejected the credentials",
            FailureCategory::AuthenticationError,
            'The embedding provider API key is missing or invalid. Update it in Knowledge settings, then retry.',
            $previous,
        );
    }

    /**
     * A provider returning a different number of vectors than texts sent, or
     * vectors of unexpected width, is unrecoverable: pairing them up anyway
     * would attach each chunk to another chunk's meaning.
     */
    public static function misalignedBatch(string $provider, int $expected, int $received): self
    {
        return new self(
            "Embedding provider [{$provider}] returned {$received} vectors for {$expected} inputs",
            FailureCategory::EmbeddingProviderError,
            'The embedding service returned a malformed response. Retry the source; if it persists, contact support.',
        );
    }

    public static function dimensionMismatch(int $expected, int $received): self
    {
        return new self(
            "Embedding dimension mismatch: knowledge base expects {$expected}, provider returned {$received}",
            FailureCategory::EmbeddingProviderError,
            'The configured embedding model does not match this knowledge base. Re-index the knowledge base to rebuild its vectors.',
        );
    }
}
