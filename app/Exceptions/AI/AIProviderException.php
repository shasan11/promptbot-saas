<?php

namespace App\Exceptions\AI;

use RuntimeException;
use Throwable;

class AIProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        string $safeMessage,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeMessage, 0, $previous);
    }
}
