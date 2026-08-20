<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\ArticleStatus;
use App\Models\Knowledge\Concerns\HasKnowledgeTags;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A longer, authored piece of knowledge (a policy, a how-to, an internal
 * procedure) that must pass an editorial review before it can answer
 * questions. This is what separates an article from a document or FAQ: the
 * others are ready for AI the moment they finish processing, but an article
 * is deliberately withheld until someone other than the author approves it.
 */
class KnowledgeArticle extends Model
{
    use HasKnowledgeTags, HasPublicUuid, SoftDeletes;

    protected $table = 'knowledge_articles';

    protected $fillable = [
        'knowledge_base_id', 'knowledge_source_id', 'knowledge_collection_id',
        'title', 'slug', 'summary', 'body', 'status', 'language', 'allow_ai_access',
        'author_id', 'reviewer_id', 'published_by', 'review_note',
        'published_at', 'review_requested_at', 'reviewed_at', 'archived_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'allow_ai_access' => 'boolean',
            'indexed_at' => 'datetime',
            'published_at' => 'datetime',
            'review_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCollection::class, 'knowledge_collection_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'knowledge_article_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeArticleVersion::class)->orderByDesc('version_number');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Published->value);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where(fn (Builder $inner) => $inner
            ->where('title', 'like', "%{$term}%")
            ->orWhere('summary', 'like', "%{$term}%")
            ->orWhere('body', 'like', "%{$term}%"));
    }

    /** The text handed to the chunker. Title and summary are prepended so every chunk carries them for context. */
    public function retrievableText(): string
    {
        return trim(implode("\n\n", array_filter([$this->title, $this->summary, $this->body])));
    }

    public function chunkOwnerKey(): string
    {
        return 'article:'.$this->id;
    }

    /** Retrievability requires both an approved status and the author's own AI-access toggle. */
    public function isRetrievable(): bool
    {
        return $this->status->isRetrievable() && $this->allow_ai_access;
    }
}
