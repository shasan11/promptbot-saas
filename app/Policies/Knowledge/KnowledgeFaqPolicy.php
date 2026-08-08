<?php

namespace App\Policies\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Models\Knowledge\KnowledgeFaq;
use App\Models\User;
use App\Services\Knowledge\KnowledgePermissionService;

class KnowledgeFaqPolicy
{
    public function __construct(private readonly KnowledgePermissionService $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('knowledge.sources.view');
    }

    public function view(User $actor, KnowledgeFaq $faq): bool
    {
        return $actor->can('knowledge.sources.view') && $this->canReach($actor, $faq);
    }

    public function create(User $actor): bool
    {
        return $actor->can('knowledge.sources.create');
    }

    public function update(User $actor, KnowledgeFaq $faq): bool
    {
        return $actor->can('knowledge.sources.update')
            && $this->canReach($actor, $faq, AccessLevel::Contribute);
    }

    /**
     * Publishing is what makes an FAQ answer real customers, so it is gated
     * above ordinary editing of a draft.
     */
    public function publish(User $actor, KnowledgeFaq $faq): bool
    {
        return $actor->can('knowledge.update')
            && $this->canReach($actor, $faq, AccessLevel::Contribute);
    }

    public function delete(User $actor, KnowledgeFaq $faq): bool
    {
        return $actor->can('knowledge.sources.delete')
            && $this->canReach($actor, $faq, AccessLevel::Contribute);
    }

    private function canReach(User $actor, KnowledgeFaq $faq, AccessLevel $level = AccessLevel::Read): bool
    {
        return in_array($faq->knowledge_base_id, $this->permissions->allowedKnowledgeBaseIds($actor, $level), true);
    }
}
