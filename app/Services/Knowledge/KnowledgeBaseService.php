<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\GranteeType;
use App\Enums\Knowledge\KnowledgeBaseStatus;
use App\Events\Knowledge\KnowledgeBaseAccessChanged;
use App\Models\Knowledge\KnowledgeAccessGrant;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeChunk;
use App\Models\Knowledge\KnowledgeCollection;
use App\Models\User;
use App\Services\Knowledge\Embedding\EmbeddingProviderFactory;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creating, configuring and retiring knowledge bases.
 */
class KnowledgeBaseService
{
    public function __construct(
        private readonly TenantAuditLogService $auditLog,
        private readonly KnowledgeLimitService $limits,
        private readonly KnowledgeIndexService $index,
        private readonly EmbeddingProviderFactory $providers,
    ) {}

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes, ?User $actor): KnowledgeBase
    {
        $this->limits->assertWithinLimit('knowledge_bases');

        $provider = $this->providers->make($attributes['embedding_provider'] ?? (string) config('knowledge.embeddings.default_provider'));

        $base = KnowledgeBase::create(array_merge($attributes, [
            'slug' => $this->uniqueSlug($attributes['name']),
            'status' => KnowledgeBaseStatus::Draft,
            // Dimensions are taken from the provider, never from user input:
            // a mismatch here would produce vectors that cannot be compared and
            // a base that silently retrieves nothing.
            'embedding_provider' => $provider->name(),
            'embedding_model' => $provider->model(),
            'embedding_dimensions' => $provider->dimensions(),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]));

        $this->auditLog->record(
            'knowledge.base_created',
            $actor,
            "Created knowledge base \"{$base->name}\"",
            $base,
            newValues: ['name' => $base->name, 'visibility' => $base->visibility->value],
        );

        return $base;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{base: KnowledgeBase, requires_reindex: bool}
     */
    public function update(KnowledgeBase $base, array $attributes, ?User $actor): array
    {
        $before = $base->only([
            'name', 'description', 'visibility', 'status', 'embedding_provider', 'embedding_model',
            'chunking_strategy', 'chunk_size', 'chunk_overlap', 'retrieval_mode', 'top_k',
            'similarity_threshold', 'reranking_enabled',
        ]);

        $requiresReindex = false;

        // Switching the embedding model invalidates every existing vector.
        // Rather than let the base quietly return nonsense, the change bumps the
        // version, clears the vectors and flags the base for re-indexing.
        if (isset($attributes['embedding_provider']) && $attributes['embedding_provider'] !== $base->embedding_provider) {
            $provider = $this->providers->make($attributes['embedding_provider']);

            $attributes['embedding_model'] = $provider->model();
            $attributes['embedding_dimensions'] = $provider->dimensions();
            $attributes['embedding_version'] = $base->embedding_version + 1;
            $requiresReindex = true;
        }

        // A manual status change (Draft/Active/Disabled — Archived has its own
        // dedicated action) must keep chunk retrievability in lock-step, the
        // same way archive()/restore() already do. Without this, disabling a
        // base here would change its label but leave its content answering
        // questions, and re-enabling it would leave chunks stuck withdrawn.
        $statusChanging = isset($attributes['status']) && $attributes['status'] !== $base->status->value;
        $wasRetrievable = $base->status->isRetrievable();
        $willBeRetrievable = $statusChanging ? KnowledgeBaseStatus::from($attributes['status'])->isRetrievable() : $wasRetrievable;

        $attributes['updated_by'] = $actor?->id;

        DB::transaction(function () use ($base, $attributes, $statusChanging, $wasRetrievable, $willBeRetrievable): void {
            $base->update($attributes);

            if ($statusChanging && $wasRetrievable && ! $willBeRetrievable) {
                KnowledgeChunk::query()->where('knowledge_base_id', $base->id)->update(['is_retrievable' => false]);
            }

            if ($statusChanging && ! $wasRetrievable && $willBeRetrievable) {
                KnowledgeChunk::query()
                    ->where('knowledge_base_id', $base->id)
                    ->where('embedding_status', KnowledgeChunk::EMBEDDING_READY)
                    ->update(['is_retrievable' => true]);
            }
        });

        if ($requiresReindex) {
            $this->index->markBaseForReindex($base->id);
        }

        $this->auditLog->record(
            'knowledge.base_updated',
            $actor,
            "Updated knowledge base \"{$base->name}\"",
            $base,
            oldValues: $before,
            newValues: $attributes,
        );

        if (array_key_exists('visibility', $attributes)) {
            KnowledgeBaseAccessChanged::dispatch($base->id);
        }

        return ['base' => $base->refresh(), 'requires_reindex' => $requiresReindex];
    }

    /**
     * Archives a base and takes it out of retrieval immediately.
     *
     * Archiving is reversible and keeps the data; what it must NOT do is leave
     * the content answering questions. The chunks are withdrawn in the same
     * transaction as the status change.
     */
    public function archive(KnowledgeBase $base, ?User $actor): void
    {
        DB::transaction(function () use ($base): void {
            $base->update(['status' => KnowledgeBaseStatus::Archived]);

            KnowledgeChunk::query()
                ->where('knowledge_base_id', $base->id)
                ->update(['is_retrievable' => false]);
        });

        $this->auditLog->record('knowledge.base_archived', $actor, "Archived knowledge base \"{$base->name}\"", $base);
    }

    public function restore(KnowledgeBase $base, ?User $actor): void
    {
        DB::transaction(function () use ($base): void {
            $base->update(['status' => KnowledgeBaseStatus::Active]);

            KnowledgeChunk::query()
                ->where('knowledge_base_id', $base->id)
                ->where('embedding_status', KnowledgeChunk::EMBEDDING_READY)
                ->update(['is_retrievable' => true]);
        });

        $this->auditLog->record('knowledge.base_restored', $actor, "Restored knowledge base \"{$base->name}\"", $base);
    }

    /**
     * Soft-deletes a base. Retrieval stops at once even though the rows remain,
     * which is the behaviour the module guarantees for any deleted resource.
     * Permanent removal of files and chunks runs asynchronously via PurgeKnowledgeJob.
     */
    public function delete(KnowledgeBase $base, ?User $actor): void
    {
        DB::transaction(function () use ($base): void {
            KnowledgeChunk::query()
                ->where('knowledge_base_id', $base->id)
                ->update(['is_retrievable' => false]);

            $base->delete();
        });

        $this->auditLog->record(
            'knowledge.base_deleted',
            $actor,
            "Deleted knowledge base \"{$base->name}\"",
            subjectType: KnowledgeBase::class,
            subjectLabel: $base->name,
            metadata: ['uuid' => $base->uuid, 'chunks' => $base->chunk_count, 'documents' => $base->document_count],
        );
    }

    /**
     * What deleting this base would destroy — shown on the confirmation dialog,
     * because "26 documents, 8,230 chunks, 3 agents" is the difference between
     * an informed decision and an accident.
     *
     * @return array<string, int|array>
     */
    public function deletionImpact(KnowledgeBase $base): array
    {
        return [
            'sources' => $base->sources()->count(),
            'documents' => $base->documents()->count(),
            'website_pages' => $base->websitePages()->count(),
            'faqs' => $base->faqs()->count(),
            'collections' => $base->collections()->count(),
            'chunks' => $base->chunks()->count(),
            'storage_bytes' => (int) $base->documents()->sum('file_size'),
            'agents' => $base->accessGrants()->where('grantee_type', GranteeType::Agent->value)->count(),
            'agent_names' => $base->accessGrants()
                ->where('grantee_type', GranteeType::Agent->value)
                ->pluck('grantee_label')
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * Replaces the base's access grants wholesale.
     *
     * @param  array<int, array{grantee_type: string, grantee_id?: int|null, grantee_key?: string|null, grantee_label?: string|null, access_level?: string, collection_id?: int|null}>  $grants
     */
    public function syncAccessGrants(KnowledgeBase $base, array $grants, ?User $actor): void
    {
        $before = $base->accessGrants()->get()->map(fn (KnowledgeAccessGrant $g) => $g->dedupe_key)->all();

        DB::transaction(function () use ($base, $grants, $actor): void {
            $base->accessGrants()->delete();

            foreach ($grants as $grant) {
                KnowledgeAccessGrant::create([
                    'knowledge_base_id' => $base->id,
                    'knowledge_collection_id' => $grant['collection_id'] ?? null,
                    'grantee_type' => $grant['grantee_type'],
                    'grantee_id' => $grant['grantee_id'] ?? null,
                    'grantee_key' => $grant['grantee_key'] ?? null,
                    'grantee_label' => $grant['grantee_label'] ?? null,
                    'access_level' => $grant['access_level'] ?? AccessLevel::Read->value,
                    'created_by' => $actor?->id,
                ]);
            }
        });

        // Synchronous, not queued: the next request must not be answered from a
        // cached allow-list that still contains a revoked grant.
        KnowledgeBaseAccessChanged::dispatch($base->id);

        $this->auditLog->record(
            'knowledge.access_changed',
            $actor,
            "Changed access for knowledge base \"{$base->name}\"",
            $base,
            oldValues: ['grant_count' => count($before)],
            newValues: ['grant_count' => count($grants)],
        );
    }

    public function createCollection(KnowledgeBase $base, array $attributes, ?User $actor): KnowledgeCollection
    {
        $parent = isset($attributes['parent_id'])
            ? KnowledgeCollection::query()->where('knowledge_base_id', $base->id)->find($attributes['parent_id'])
            : null;

        $depth = $parent ? $parent->depth + 1 : 0;

        // Depth is capped rather than unbounded: deep hierarchies are
        // unnavigable and make inherited access impossible to reason about.
        if ($depth > KnowledgeCollection::MAX_DEPTH) {
            throw new \InvalidArgumentException(
                'Collections can be nested at most '.(KnowledgeCollection::MAX_DEPTH + 1).' levels deep.'
            );
        }

        return KnowledgeCollection::create(array_merge($attributes, [
            'knowledge_base_id' => $base->id,
            'parent_id' => $parent?->id,
            'depth' => $depth,
            'slug' => $this->uniqueCollectionSlug($base, $attributes['name']),
            'created_by' => $actor?->id,
        ]));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'knowledge-base';
        $slug = $base;
        $suffix = 1;

        while (KnowledgeBase::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }

    private function uniqueCollectionSlug(KnowledgeBase $base, string $name): string
    {
        $root = Str::slug($name) ?: 'collection';
        $slug = $root;
        $suffix = 1;

        while (KnowledgeCollection::withTrashed()->where('knowledge_base_id', $base->id)->where('slug', $slug)->exists()) {
            $slug = $root.'-'.++$suffix;
        }

        return $slug;
    }
}
