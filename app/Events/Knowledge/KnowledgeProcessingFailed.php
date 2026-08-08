<?php

namespace App\Events\Knowledge;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KnowledgeProcessingFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $documentId,
        public readonly string $failureUuid,
    ) {}
}
