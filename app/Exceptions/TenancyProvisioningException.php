<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class TenancyProvisioningException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $step,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
