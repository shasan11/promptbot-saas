<?php

namespace App\Services\AI\Exceptions;

use Throwable;

class AIModelNotAvailableException extends AIException
{
    public static function make(string $provider, string $model, ?Throwable $previous = null): self
    {
        return new self(
            "Provider [{$provider}] does not recognize model [{$model}].",
            'model_not_available',
            'The selected model is not available from this provider. Choose a different model.',
            $previous,
        );
    }
}
