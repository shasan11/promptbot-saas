<?php

namespace App\Policies\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\User;
use App\Services\Knowledge\KnowledgePermissionService;

/**
 * Two independent checks guard every knowledge base, and both must pass:
 *
 *   1. the RBAC permission — may this user do this *kind* of thing at all;
 *   2. the access grant    — may they do it to *this particular* base.
 *
 * Holding knowledge.update does not imply access to a private HR knowledge
 * base, and being granted access to it does not imply the right to delete it.
 */
class KnowledgeBasePolicy
{
    public function __construct(private readonly KnowledgePermissionService $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('knowledge.view');
    }

    public function view(User $actor, KnowledgeBase $base): bool
    {
        return $actor->can('knowledge.view')
            && $this->permissions->userCanAccessBase($actor, $base);
    }

    public function create(User $actor): bool
    {
        return $actor->can('knowledge.create');
    }

    public function update(User $actor, KnowledgeBase $base): bool
    {
        return $actor->can('knowledge.update')
            && $this->permissions->userCanAccessBase($actor, $base, AccessLevel::Manage);
    }

    public function delete(User $actor, KnowledgeBase $base): bool
    {
        return $actor->can('knowledge.delete')
            && $this->permissions->userCanAccessBase($actor, $base, AccessLevel::Manage);
    }

    public function forceDelete(User $actor, KnowledgeBase $base): bool
    {
        // Permanent deletion destroys files, chunks and audit-relevant history,
        // so it needs the administrative capability rather than plain delete.
        return $actor->can('knowledge.manage') && $actor->can('knowledge.delete');
    }

    public function managePermissions(User $actor, KnowledgeBase $base): bool
    {
        return $actor->can('knowledge.permissions.manage')
            && $this->permissions->userCanAccessBase($actor, $base, AccessLevel::Manage);
    }

    public function manageSettings(User $actor, KnowledgeBase $base): bool
    {
        return $actor->can('knowledge.settings.manage')
            && $this->permissions->userCanAccessBase($actor, $base, AccessLevel::Manage);
    }

    public function testRetrieval(User $actor, KnowledgeBase $base): bool
    {
        return $actor->can('knowledge.retrieval.test')
            && $this->permissions->userCanAccessBase($actor, $base);
    }

    /**
     * The retrieval debugger exposes embedding internals, discarded candidates
     * and threshold decisions. Useful to whoever tunes the base; noise (and a
     * quiet information leak about content that did not match) to everyone else.
     */
    public function debugRetrieval(User $actor, KnowledgeBase $base): bool
    {
        return $actor->can('knowledge.manage')
            && $this->permissions->userCanAccessBase($actor, $base);
    }

    public function viewAnalytics(User $actor, KnowledgeBase $base): bool
    {
        return $actor->can('knowledge.analytics.view')
            && $this->permissions->userCanAccessBase($actor, $base);
    }

    public function reindex(User $actor, KnowledgeBase $base): bool
    {
        return $actor->can('knowledge.reindex')
            && $this->permissions->userCanAccessBase($actor, $base, AccessLevel::Manage);
    }
}
