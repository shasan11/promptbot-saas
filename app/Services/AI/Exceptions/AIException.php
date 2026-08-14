<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;
use Throwable;

class AIException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $code_,
        private readonly string $operatorMessage,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->code_;
    }

    /** Safe to persist/display — never the raw provider response. */
    public function operatorMessage(): string
    {
        return $this->operatorMessage;
    }
}
