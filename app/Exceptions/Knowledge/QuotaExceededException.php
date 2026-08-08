<?php

namespace App\Exceptions\Knowledge;

use App\Enums\Knowledge\FailureCategory;

class QuotaExceededException extends KnowledgeException
{
    public static function forLimit(string $limit, int|float $used, int|float $allowed): self
    {
        return new self(
            "Knowledge limit [{$limit}] exceeded: {$used} of {$allowed}",
            FailureCategory::QuotaExceeded,
            'This workspace has reached its '.str_replace('_', ' ', $limit).' limit. '
            .'Upgrade the plan or remove unused knowledge to continue.',
        );
    }
}
