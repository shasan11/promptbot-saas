<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\FreshnessState;
use App\Enums\Knowledge\SourcePriority;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Enums\Knowledge\SyncFrequency;
use App\Enums\Knowledge\SyncStatus;
use App\Models\Knowledge\Concerns\HasKnowledgeTags;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Where a piece of knowledge came from. Sources are the unit an operator
 * manages (sync it, retry it, revoke it); documents/pages/FAQs are what they
 * produce, and chunks are what retrieval reads.
 */
class KnowledgeSource extends Model
{
    use HasKnowledgeTags, HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'knowledge_base_id', 'knowledge_collection_id', 'source_type', 'name', 'description',
        'configuration', 'status', 'priority', 'sync_frequency', 'review_every_days',
        'effective_from', 'effective_until', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'status' => SourceStatus::class,
            'priority' => SourcePriority::class,
            'sync_status' => SyncStatus::class,
            'sync_frequency' => SyncFrequency::class,
            'configuration' => 'array',
            'last_synced_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
            'next_sync_at' => 'datetime',
            'review_due_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    // -- Relationships -------------------------------------------------------

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCollection::class, 'knowledge_collection_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function websitePages(): HasMany
    {
        return $this->hasMany(KnowledgeWebsitePage::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(KnowledgeFaq::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeArticle::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(KnowledgeSyncRun::class)->latest();
    }

    public function failures(): HasMany
    {
        return $this->hasMany(KnowledgeFailure::class)->latest();
    }

    public function credential(): HasOne
    {
        return $this->hasOne(KnowledgeSourceCredential::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -- Scopes --------------------------------------------------------------

    public function scopeRetrievable(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (SourceStatus $s) => $s->value,
            array_filter(SourceStatus::cases(), fn (SourceStatus $s) => $s->isRetrievable())
        ));
    }

    public function scopeDueForSync(Builder $query): Builder
    {
        return $query
            ->where('sync_frequency', '!=', SyncFrequency::Manual->value)
            ->whereNotNull('next_sync_at')
            ->where('next_sync_at', '<=', now())
            ->whereNotIn('sync_status', [SyncStatus::Queued->value, SyncStatus::Running->value])
            ->whereNotIn('status', [
                SourceStatus::Disabled->value,
                SourceStatus::Archived->value,
                // A source whose credentials expired will fail every attempt;
                // re-queueing it hourly just burns worker time and quota.
                SourceStatus::AttentionRequired->value,
            ]);
    }

    // -- Behaviour -----------------------------------------------------------

    /** Config value with dot access, e.g. `configValue('crawl.max_pages', 200)`. */
    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->configuration ?? [], $key, $default);
    }

    public function freshness(): FreshnessState
    {
        if ($this->status === SourceStatus::AttentionRequired) {
            return FreshnessState::Disconnected;
        }

        $reference = $this->last_successful_sync_at ?? $this->updated_at;
        $window = $this->review_every_days
            ?? $this->knowledgeBase?->review_every_days
            ?? config('knowledge.freshness.default_review_every_days');

        return FreshnessState::forAge($reference?->diffInDays(now()), $window);
    }

    /**
     * Whether this source's content is inside its effective window. Expired
     * knowledge ("2025 Pricing Policy") must stop answering current questions
     * even though the records remain for audit.
     */
    public function isEffectiveAt(?\DateTimeInterface $moment = null): bool
    {
        $moment ??= now();

        return ! ($this->effective_from && $this->effective_from->gt($moment))
            && ! ($this->effective_until && $this->effective_until->lt($moment));
    }
}
