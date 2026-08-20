<?php

namespace App\Policies\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Models\Knowledge\KnowledgeArticle;
use App\Models\User;
use App\Services\Knowledge\KnowledgePermissionService;

class KnowledgeArticlePolicy
{
    public function __construct(private readonly KnowledgePermissionService $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('knowledge.sources.view');
    }

    public function view(User $actor, KnowledgeArticle $article): bool
    {
        return $actor->can('knowledge.sources.view') && $this->canReach($actor, $article);
    }

    public function create(User $actor): bool
    {
        return $actor->can('knowledge.sources.create');
    }

    public function update(User $actor, KnowledgeArticle $article): bool
    {
        return $actor->can('knowledge.sources.update')
            && $this->canReach($actor, $article, AccessLevel::Contribute);
    }

    public function submitForReview(User $actor, KnowledgeArticle $article): bool
    {
        return $actor->can('knowledge.sources.update')
            && $this->canReach($actor, $article, AccessLevel::Contribute);
    }

    /**
     * Approving/rejecting/archiving/restoring all change whether the article
     * can answer real questions, so — like FAQ publishing — they require the
     * stronger knowledge-management permission, not just content-editing.
     */
    public function approve(User $actor, KnowledgeArticle $article): bool
    {
        return $actor->can('knowledge.update')
            && $this->canReach($actor, $article, AccessLevel::Contribute);
    }

    public function reject(User $actor, KnowledgeArticle $article): bool
    {
        return $this->approve($actor, $article);
    }

    public function archive(User $actor, KnowledgeArticle $article): bool
    {
        return $this->approve($actor, $article);
    }

    public function restore(User $actor, KnowledgeArticle $article): bool
    {
        return $this->approve($actor, $article);
    }

    public function delete(User $actor, KnowledgeArticle $article): bool
    {
        return $actor->can('knowledge.sources.delete')
            && $this->canReach($actor, $article, AccessLevel::Contribute);
    }

    private function canReach(User $actor, KnowledgeArticle $article, AccessLevel $level = AccessLevel::Read): bool
    {
        return in_array($article->knowledge_base_id, $this->permissions->allowedKnowledgeBaseIds($actor, $level), true);
    }
}
