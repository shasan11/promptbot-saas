<?php

namespace App\Events\Knowledge;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Carries IDs rather than models on purpose: listeners run on the queue, and a
 * serialised Eloquent model would be restored against whichever database
 * connection is active at handling time — which, in a database-per-tenant
 * application, is not necessarily the tenant that raised it.
 */
class KnowledgeProcessingCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $documentId,
        public readonly int $chunkCount,
    ) {}
}
