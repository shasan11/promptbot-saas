<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\FaqStatus;
use App\Models\Knowledge\Concerns\HasKnowledgeTags;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeFaq extends Model
{
    use HasKnowledgeTags, HasPublicUuid, SoftDeletes;

    protected $table = 'knowledge_faqs';

    protected $fillable = [
        'knowledge_base_id', 'knowledge_source_id', 'knowledge_collection_id',
        'question', 'answer', 'category', 'language', 'status', 'priority',
        'effective_from', 'effective_until', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => FaqStatus::class,
            'indexed_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
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
        return $this->hasMany(KnowledgeChunk::class, 'knowledge_faq_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeFaqVersion::class)->orderByDesc('version_number');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', FaqStatus::Published->value);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where(fn (Builder $inner) => $inner
            ->where('question', 'like', "%{$term}%")
            ->orWhere('answer', 'like', "%{$term}%")
            ->orWhere('category', 'like', "%{$term}%"));
    }

    /**
     * The text that actually gets embedded. Question and answer are kept
     * together in a single chunk: embedding the answer alone loses the phrasing
     * users actually search with, and embedding the question alone retrieves a
     * chunk with no information in it.
     */
    public function retrievableText(): string
    {
        return trim("Q: {$this->question}\n\nA: {$this->answer}");
    }

    public function chunkOwnerKey(): string
    {
        return 'faq:'.$this->id;
    }

    public function isEffectiveAt(?\DateTimeInterface $moment = null): bool
    {
        $moment ??= now();

        return ! ($this->effective_from && $this->effective_from->gt($moment))
            && ! ($this->effective_until && $this->effective_until->lt($moment));
    }
}
