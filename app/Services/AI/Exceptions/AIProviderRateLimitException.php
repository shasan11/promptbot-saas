<?php

namespace App\Services\AI\Exceptions;

use Throwable;

class AIProviderRateLimitException extends AIException
{
    public static function make(string $provider, ?Throwable $previous = null): self
    {
        return new self(
            "Provider [{$provider}] rate-limited the request.",
            'provider_rate_limit',
            'The provider is rate-limiting requests. Try again shortly, or configure a fallback provider.',
            $previous,
        );
    }
}
