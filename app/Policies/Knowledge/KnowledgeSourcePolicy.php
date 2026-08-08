<?php

namespace App\Policies\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\User;
use App\Services\Knowledge\KnowledgePermissionService;

class KnowledgeSourcePolicy
{
    public function __construct(private readonly KnowledgePermissionService $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('knowledge.sources.view');
    }

    public function view(User $actor, KnowledgeSource $source): bool
    {
        return $actor->can('knowledge.sources.view') && $this->canReach($actor, $source);
    }

    public function create(User $actor): bool
    {
        return $actor->can('knowledge.sources.create');
    }

    public function update(User $actor, KnowledgeSource $source): bool
    {
        return $actor->can('knowledge.sources.update')
            && $this->canReach($actor, $source, AccessLevel::Contribute);
    }

    public function delete(User $actor, KnowledgeSource $source): bool
    {
        return $actor->can('knowledge.sources.delete')
            && $this->canReach($actor, $source, AccessLevel::Manage);
    }

    public function sync(User $actor, KnowledgeSource $source): bool
    {
        return $actor->can('knowledge.sync')
            && $this->canReach($actor, $source, AccessLevel::Contribute);
    }

    public function reindex(User $actor, KnowledgeSource $source): bool
    {
        return $actor->can('knowledge.reindex')
            && $this->canReach($actor, $source, AccessLevel::Contribute);
    }

    /**
     * Reconnecting a source means writing credentials for an external system,
     * which is a higher bar than editing its crawl rules.
     */
    public function manageCredentials(User $actor, KnowledgeSource $source): bool
    {
        return $actor->can('knowledge.manage')
            && $this->canReach($actor, $source, AccessLevel::Manage);
    }

    private function canReach(User $actor, KnowledgeSource $source, AccessLevel $level = AccessLevel::Read): bool
    {
        return in_array(
            $source->knowledge_base_id,
            $this->permissions->allowedKnowledgeBaseIds($actor, $level),
            true
        );
    }
}
