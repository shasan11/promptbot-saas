<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\ChunkingStrategy;
use App\Enums\Knowledge\KnowledgeBaseStatus;
use App\Enums\Knowledge\KnowledgeVisibility;
use App\Enums\Knowledge\RetrievalMode;
use App\Models\Knowledge\Concerns\HasKnowledgeTags;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A logical container of knowledge — "Customer Support", "Internal HR".
 *
 * The knowledge base owns the retrieval contract: which embedding model its
 * vectors were produced with, how content is chunked, and how search behaves.
 * Those settings are copied onto chunks at index time, so changing them here
 * affects new content immediately and existing content only after a re-index.
 */
class KnowledgeBase extends Model
{
    use HasKnowledgeTags, HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'status', 'visibility', 'icon', 'color',
        'default_language', 'supported_languages',
        'embedding_provider', 'embedding_model', 'embedding_dimensions', 'embedding_version',
        'chunking_strategy', 'chunk_size', 'chunk_overlap',
        'retrieval_mode', 'top_k', 'candidate_pool', 'similarity_threshold',
        'reranking_enabled', 'max_context_tokens', 'allow_cross_source_retrieval',
        'prefer_recent_content', 'require_citations', 'exclude_expired_content',
        'review_every_days', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => KnowledgeBaseStatus::class,
            'visibility' => KnowledgeVisibility::class,
            'chunking_strategy' => ChunkingStrategy::class,
            'retrieval_mode' => RetrievalMode::class,
            'supported_languages' => 'array',
            'similarity_threshold' => 'float',
            'reranking_enabled' => 'boolean',
            'allow_cross_source_retrieval' => 'boolean',
            'prefer_recent_content' => 'boolean',
            'require_citations' => 'boolean',
            'exclude_expired_content' => 'boolean',
            'last_indexed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'counters_refreshed_at' => 'datetime',
        ];
    }

    // -- Relationships -------------------------------------------------------

    public function sources(): HasMany
    {
        return $this->hasMany(KnowledgeSource::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(KnowledgeCollection::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(KnowledgeFaq::class);
    }

    public function websitePages(): HasMany
    {
        return $this->hasMany(KnowledgeWebsitePage::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(KnowledgeAccessGrant::class);
    }

    public function retrievalLogs(): HasMany
    {
        return $this->hasMany(KnowledgeRetrievalLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // -- Scopes --------------------------------------------------------------

    public function scopeRetrievable(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (KnowledgeBaseStatus $status) => $status->value,
            array_filter(KnowledgeBaseStatus::cases(), fn (KnowledgeBaseStatus $s) => $s->isRetrievable())
        ));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', "%{$term}%"));
        });
    }

    // -- Behaviour -----------------------------------------------------------

    /**
     * The vector contract for this base. Chunks embedded under a different
     * signature are not comparable and are excluded from semantic retrieval
     * until they are re-indexed.
     */
    public function embeddingSignature(): string
    {
        return "{$this->embedding_provider}:{$this->embedding_model}:{$this->embedding_dimensions}:v{$this->embedding_version}";
    }

    public function isRetrievable(): bool
    {
        return $this->status->isRetrievable() && $this->deleted_at === null;
    }
}
