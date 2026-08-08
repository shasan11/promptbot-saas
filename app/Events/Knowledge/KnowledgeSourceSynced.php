<?php

namespace App\Events\Knowledge;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KnowledgeSourceSynced
{
    use Dispatchable, SerializesModels;

    /** @param  array<string, int>  $summary */
    public function __construct(
        public readonly int $sourceId,
        public readonly int $syncRunId,
        public readonly array $summary,
        public readonly bool $succeeded,
    ) {}
}
