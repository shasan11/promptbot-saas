<?php

namespace App\Services\AI\Exceptions;

use Throwable;

class AIProviderTimeoutException extends AIException
{
    public static function make(string $provider, ?Throwable $previous = null): self
    {
        return new self(
            "Provider [{$provider}] timed out or was unreachable.",
            'provider_timeout',
            'The provider did not respond in time. Check the base URL and network connectivity.',
            $previous,
        );
    }
}
