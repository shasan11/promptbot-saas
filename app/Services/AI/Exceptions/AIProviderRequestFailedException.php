<?php

namespace App\Services\AI\Exceptions;

use Throwable;

/**
 * Catch-all for provider failures that don't map onto a more specific
 * error code (e.g. an unexpected 5xx or a malformed response body).
 */
class AIProviderRequestFailedException extends AIException
{
    public static function make(string $provider, ?Throwable $previous = null): self
    {
        return new self(
            "Provider [{$provider}] request failed.",
            'provider_request_failed',
            'The provider request failed unexpectedly. Check the provider status and try again.',
            $previous,
        );
    }
}
