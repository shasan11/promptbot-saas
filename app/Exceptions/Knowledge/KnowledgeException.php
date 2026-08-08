<?php

namespace App\Exceptions\Knowledge;

use App\Enums\Knowledge\FailureCategory;
use RuntimeException;
use Throwable;

/**
 * Base for every failure the knowledge pipeline can record.
 *
 * Carries two distinct messages on purpose. `getMessage()` is the technical one
 * — it goes to logs and to the admin-only failure detail. `operatorMessage()`
 * is what a support agent reads on the Failed Sources page: it says what went
 * wrong in their terms and what to do next. Rendering the former to end users
 * is how "Something went wrong" screens get written.
 */
class KnowledgeException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly FailureCategory $category = FailureCategory::Unknown,
        private readonly ?string $operatorMessage = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function category(): FailureCategory
    {
        return $this->category;
    }

    public function operatorMessage(): string
    {
        return $this->operatorMessage ?? $this->category->remediation();
    }

    public function isRetryable(): bool
    {
        return $this->category->isTransient();
    }
}
