<?php

namespace App\Services\AI\Exceptions;

use Throwable;

class AIProviderAuthenticationException extends AIException
{
    public static function make(string $provider, ?Throwable $previous = null): self
    {
        return new self(
            "Provider [{$provider}] rejected the credentials.",
            'provider_authentication_failed',
            'The API key was rejected. Double-check it was copied correctly and has not expired.',
            $previous,
        );
    }
}
