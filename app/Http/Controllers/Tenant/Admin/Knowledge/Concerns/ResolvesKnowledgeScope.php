<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge\Concerns;

use App\Enums\Knowledge\AccessLevel;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\User;
use App\Services\Knowledge\KnowledgePermissionService;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared scoping for every knowledge screen.
 *
 * Each list query is constrained to the bases the current user may read, rather
 * than being fetched freely and filtered in the view. That way a user who
 * cannot see the HR knowledge base does not learn how many documents it holds
 * from a count, or that it exists at all from a filter dropdown.
 */
trait ResolvesKnowledgeScope
{
    protected function permissions(): KnowledgePermissionService
    {
        return app(KnowledgePermissionService::class);
    }

    protected function actor(): User
    {
        $user = request()->user('tenant');

        abort_if($user === null, 403);

        return $user;
    }

    /** @return array<int, int> */
    protected function allowedBaseIds(AccessLevel $level = AccessLevel::Read): array
    {
        return $this->permissions()->allowedKnowledgeBaseIds($this->actor(), $level);
    }

    /**
     * Constrains a query to the readable bases.
     *
     * When the user may read nothing, `whereRaw('1 = 0')` is used rather than
     * `whereIn(..., [])` — both return nothing, but the former is explicit
     * about the intent and cannot be accidentally optimised away by a later
     * refactor that treats an empty array as "no filter".
     */
    protected function scopeToAllowedBases(Builder $query, string $column = 'knowledge_base_id'): Builder
    {
        $ids = $this->allowedBaseIds();

        return $ids ? $query->whereIn($column, $ids) : $query->whereRaw('1 = 0');
    }

    /**
     * Resolves a knowledge base by UUID within the user's scope.
     *
     * A base the user cannot reach 404s rather than 403s: telling an
     * unauthorised user that a given UUID *exists* is itself a small disclosure,
     * and there is no legitimate flow in which they hold a valid UUID they may
     * not read.
     */
    protected function resolveBase(string $uuid, AccessLevel $level = AccessLevel::Read): KnowledgeBase
    {
        $base = KnowledgeBase::query()->where('uuid', $uuid)->first();

        if (! $base || ! in_array($base->id, $this->allowedBaseIds($level), true)) {
            throw new NotFoundHttpException;
        }

        return $base;
    }

    /**
     * Bases offered in pickers and filters.
     *
     * @return \Illuminate\Support\Collection<int, KnowledgeBase>
     */
    protected function selectableBases(AccessLevel $level = AccessLevel::Read): \Illuminate\Support\Collection
    {
        $ids = $this->allowedBaseIds($level);

        if (! $ids) {
            return collect();
        }

        return KnowledgeBase::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'status', 'default_language']);
    }
}
