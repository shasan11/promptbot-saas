<?php

namespace App\Policies\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Models\Knowledge\KnowledgeCollection;
use App\Models\User;
use App\Services\Knowledge\KnowledgePermissionService;

class KnowledgeCollectionPolicy
{
    public function __construct(private readonly KnowledgePermissionService $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('knowledge.view');
    }

    public function view(User $actor, KnowledgeCollection $collection): bool
    {
        return $actor->can('knowledge.view') && $this->canReach($actor, $collection);
    }

    public function create(User $actor): bool
    {
        return $actor->can('knowledge.update');
    }

    public function update(User $actor, KnowledgeCollection $collection): bool
    {
        return $actor->can('knowledge.update')
            && $this->canReach($actor, $collection, AccessLevel::Contribute);
    }

    public function delete(User $actor, KnowledgeCollection $collection): bool
    {
        return $actor->can('knowledge.delete')
            && $this->canReach($actor, $collection, AccessLevel::Manage);
    }

    private function canReach(User $actor, KnowledgeCollection $collection, AccessLevel $level = AccessLevel::Read): bool
    {
        return in_array(
            $collection->knowledge_base_id,
            $this->permissions->allowedKnowledgeBaseIds($actor, $level),
            true
        );
    }
}
