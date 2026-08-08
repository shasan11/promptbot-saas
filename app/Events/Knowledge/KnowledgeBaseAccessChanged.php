<?php

namespace App\Events\Knowledge;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised whenever grants or visibility change. The listener flushes
 * KnowledgePermissionService's cached allow-lists — a stale allow-list after a
 * revocation is a disclosure bug, so this must not wait for a TTL.
 */
class KnowledgeBaseAccessChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly int $knowledgeBaseId) {}
}
