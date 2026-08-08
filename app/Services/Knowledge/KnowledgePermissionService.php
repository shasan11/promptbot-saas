<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\GranteeType;
use App\Enums\Knowledge\KnowledgeVisibility;
use App\Models\Knowledge\KnowledgeAccessGrant;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeCollection;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves *what knowledge a given actor may see* into concrete ID allow-lists.
 *
 * This is the single most security-sensitive class in the module. Retrieval
 * calls it BEFORE searching and uses the result as a hard filter on the
 * candidate set, rather than searching everything and discarding forbidden
 * chunks afterwards. The distinction matters: post-filtering leaks through
 * scoring, result counts and timing even when the text itself is withheld, and
 * one missed filter in one code path exposes the content outright.
 *
 * Cross-*tenant* isolation is not this class's job — that is guaranteed
 * physically by the per-tenant database. This handles the within-workspace
 * question: which knowledge bases and collections may this user, or this AI
 * agent, read.
 */
class KnowledgePermissionService
{
    /** Keep the cache short: a revoked grant must stop working promptly. */
    private const CACHE_TTL_SECONDS = 60;

    /**
     * Knowledge base IDs a human user may read.
     *
     * Workspace-visible bases are readable by anyone holding knowledge.view.
     * Private and team-scoped bases require an explicit grant matching the user,
     * one of their teams, or one of their roles.
     *
     * @return array<int, int>
     */
    public function allowedKnowledgeBaseIds(User $user, AccessLevel $level = AccessLevel::Read): array
    {
        return Cache::remember(
            $this->cacheKey('kb', $user->id, $level->value),
            self::CACHE_TTL_SECONDS,
            fn () => $this->computeAllowedKnowledgeBaseIds($user, $level)
        );
    }

    /** @return array<int, int> */
    private function computeAllowedKnowledgeBaseIds(User $user, AccessLevel $level): array
    {
        if (! $user->can('knowledge.view')) {
            return [];
        }

        // knowledge.manage is the workspace-wide administrative capability; a
        // holder is expected to be able to audit and repair every base,
        // including private ones they were never explicitly granted.
        if ($user->can('knowledge.manage')) {
            return KnowledgeBase::query()->pluck('id')->all();
        }

        $workspaceVisible = KnowledgeBase::query()
            ->where('visibility', KnowledgeVisibility::Workspace->value)
            ->pluck('id');

        $granted = $this->grantsForUser($user)
            ->filter(fn (KnowledgeAccessGrant $grant) => $grant->access_level->allows($level))
            ->pluck('knowledge_base_id');

        // A workspace-visible base only counts toward levels above Read if the
        // user also holds a grant — visibility governs reading, not editing.
        $ids = $level === AccessLevel::Read
            ? $workspaceVisible->concat($granted)
            : $granted;

        return $ids->unique()->values()->all();
    }

    /**
     * Collection IDs the user may read within the given bases.
     *
     * `null` means "no collection restriction applies" — the user can read the
     * whole base. An empty array means the opposite: they may read only through
     * collection-scoped grants and hold none. Callers must distinguish these;
     * treating null as an empty filter would expose everything.
     *
     * @param  array<int, int>  $knowledgeBaseIds
     * @return array<int, int>|null
     */
    public function allowedCollectionIds(User $user, array $knowledgeBaseIds): ?array
    {
        if ($user->can('knowledge.manage')) {
            return null;
        }

        $grants = $this->grantsForUser($user)
            ->whereIn('knowledge_base_id', $knowledgeBaseIds);

        // Any base-wide grant (or workspace visibility) means collection
        // filtering does not narrow this user at all.
        $hasUnscopedAccess = $grants->contains(fn (KnowledgeAccessGrant $g) => $g->knowledge_collection_id === null)
            || KnowledgeBase::query()
                ->whereIn('id', $knowledgeBaseIds)
                ->where('visibility', KnowledgeVisibility::Workspace->value)
                ->exists();

        if ($hasUnscopedAccess) {
            return null;
        }

        return $this->expandCollectionSubtrees(
            $grants->pluck('knowledge_collection_id')->filter()->unique()->all()
        );
    }

    /**
     * Knowledge access for an AI agent.
     *
     * Agents are never granted knowledge implicitly. An agent with no grants
     * retrieves nothing, and a workspace-visible base does NOT make itself
     * available to every agent — "visible to staff" and "usable by a bot that
     * talks to customers" are different decisions, and conflating them is how
     * HR documents end up quoted in a support chat.
     *
     * @return array{knowledge_base_ids: array<int, int>, collection_ids: array<int, int>|null}
     */
    public function allowedForAgent(string $agentKey): array
    {
        $grants = KnowledgeAccessGrant::query()
            ->where('grantee_type', GranteeType::Agent->value)
            ->where('grantee_key', $agentKey)
            ->get();

        if ($grants->isEmpty()) {
            return ['knowledge_base_ids' => [], 'collection_ids' => []];
        }

        $baseIds = $grants->pluck('knowledge_base_id')->unique()->values()->all();

        $hasUnscoped = $grants->contains(fn (KnowledgeAccessGrant $g) => $g->knowledge_collection_id === null);

        return [
            'knowledge_base_ids' => $baseIds,
            'collection_ids' => $hasUnscoped
                ? null
                : $this->expandCollectionSubtrees($grants->pluck('knowledge_collection_id')->filter()->unique()->all()),
        ];
    }

    public function userCanAccessBase(User $user, KnowledgeBase $base, AccessLevel $level = AccessLevel::Read): bool
    {
        return in_array($base->id, $this->allowedKnowledgeBaseIds($user, $level), true);
    }

    /**
     * Every grant that applies to this user: direct, via a team, or via a role.
     */
    private function grantsForUser(User $user): Collection
    {
        $teamIds = $user->relationLoaded('teams')
            ? $user->teams->pluck('id')->all()
            : $user->teams()->pluck('teams.id')->all();

        $roleIds = $user->roles()->pluck('roles.id')->all();

        return KnowledgeAccessGrant::query()
            ->where(function ($query) use ($user, $teamIds, $roleIds): void {
                $query
                    ->where(fn ($q) => $q->where('grantee_type', GranteeType::User->value)->where('grantee_id', $user->id))
                    ->orWhere(fn ($q) => $q->where('grantee_type', GranteeType::Team->value)->whereIn('grantee_id', $teamIds ?: [0]))
                    ->orWhere(fn ($q) => $q->where('grantee_type', GranteeType::Role->value)->whereIn('grantee_id', $roleIds ?: [0]));
            })
            ->get();
    }

    /**
     * Access to a collection cascades to everything nested beneath it.
     *
     * @param  array<int, int>  $collectionIds
     * @return array<int, int>
     */
    private function expandCollectionSubtrees(array $collectionIds): array
    {
        if (! $collectionIds) {
            return [];
        }

        return KnowledgeCollection::query()
            ->whereIn('id', $collectionIds)
            ->get()
            ->flatMap(fn (KnowledgeCollection $collection) => $collection->descendantIds())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Drops cached allow-lists. Called whenever grants, visibility or role
     * assignments change — a stale allow-list is a security bug, not a
     * performance one, so this errs toward flushing broadly.
     */
    public function flush(): void
    {
        // The tagged-cache API is unavailable on the database store this app
        // ships with, so keys are versioned by generation instead: bumping the
        // generation orphans every derived key at once, and the old entries age
        // out on their own 60-second TTL. Two concurrent flushes may settle on
        // the same new generation, which is harmless — both still invalidate
        // everything written under the previous one.
        Cache::forever($this->generationKey(), $this->generation() + 1);
    }

    private function cacheKey(string $scope, int|string $actorId, string $level): string
    {
        return $this->cachePrefix().":{$this->generation()}:{$scope}:{$actorId}:{$level}";
    }

    private function cachePrefix(): string
    {
        $tenantId = tenancy()->initialized ? tenant('id') : 'central';

        return "knowledge-permissions:{$tenantId}";
    }

    private function generationKey(): string
    {
        return $this->cachePrefix().':generation';
    }

    private function generation(): int
    {
        return (int) Cache::get($this->generationKey(), 1);
    }
}
