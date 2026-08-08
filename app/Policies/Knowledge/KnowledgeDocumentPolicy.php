<?php

namespace App\Policies\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\User;
use App\Services\Knowledge\KnowledgePermissionService;

class KnowledgeDocumentPolicy
{
    public function __construct(private readonly KnowledgePermissionService $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('knowledge.sources.view');
    }

    public function view(User $actor, KnowledgeDocument $document): bool
    {
        return $actor->can('knowledge.sources.view') && $this->canReach($actor, $document);
    }

    public function create(User $actor): bool
    {
        return $actor->can('knowledge.documents.upload');
    }

    public function update(User $actor, KnowledgeDocument $document): bool
    {
        return $actor->can('knowledge.sources.update')
            && $this->canReach($actor, $document, AccessLevel::Contribute);
    }

    /**
     * Downloading returns the original bytes — a stronger disclosure than
     * reading extracted text in the UI, and separately auditable, so it has its
     * own permission rather than riding on view.
     */
    public function download(User $actor, KnowledgeDocument $document): bool
    {
        return $actor->can('knowledge.documents.download') && $this->canReach($actor, $document);
    }

    public function delete(User $actor, KnowledgeDocument $document): bool
    {
        return $actor->can('knowledge.documents.delete')
            && $this->canReach($actor, $document, AccessLevel::Contribute);
    }

    public function replace(User $actor, KnowledgeDocument $document): bool
    {
        return $actor->can('knowledge.documents.upload')
            && $this->canReach($actor, $document, AccessLevel::Contribute);
    }

    public function reindex(User $actor, KnowledgeDocument $document): bool
    {
        return $actor->can('knowledge.reindex')
            && $this->canReach($actor, $document, AccessLevel::Contribute);
    }

    public function restoreVersion(User $actor, KnowledgeDocument $document): bool
    {
        return $actor->can('knowledge.sources.update')
            && $this->canReach($actor, $document, AccessLevel::Contribute);
    }

    private function canReach(User $actor, KnowledgeDocument $document, AccessLevel $level = AccessLevel::Read): bool
    {
        if (! in_array($document->knowledge_base_id, $this->permissions->allowedKnowledgeBaseIds($actor, $level), true)) {
            return false;
        }

        // A user whose access is scoped to specific collections must not reach a
        // document filed outside them, nor one filed in no collection at all.
        $allowedCollections = $this->permissions->allowedCollectionIds($actor, [$document->knowledge_base_id]);

        return $allowedCollections === null
            || ($document->knowledge_collection_id !== null
                && in_array($document->knowledge_collection_id, $allowedCollections, true));
    }
}
