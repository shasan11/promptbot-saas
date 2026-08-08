<?php

namespace App\Listeners\Knowledge;

use App\Events\Knowledge\KnowledgeBaseAccessChanged;
use App\Services\Knowledge\KnowledgePermissionService;

/**
 * Runs synchronously and deliberately so: a revoked grant must stop working
 * before the response that revoked it is rendered, not whenever a worker gets
 * to it.
 */
class FlushKnowledgePermissionCache
{
    public function __construct(private readonly KnowledgePermissionService $permissions) {}

    public function handle(KnowledgeBaseAccessChanged $event): void
    {
        $this->permissions->flush();
    }
}
