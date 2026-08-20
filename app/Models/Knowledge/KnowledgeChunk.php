<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\SourcePriority;
use App\Enums\Knowledge\SourceType;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The retrieval unit. Everything else in this module exists to produce these
 * rows correctly and to decide which of them a given actor may see.
 *
 * Chunks carry denormalised copies of their parent's retrieval-relevant
 * attributes (source type, priority, effective window, collection). That
 * duplication is deliberate: the candidate query runs on every single
 * retrieval request and joining four parent tables to evaluate visibility
 * would dominate its cost.
 */
class KnowledgeChunk extends Model
{
    use HasPublicUuid;

    public const EMBEDDING_PENDING = 'pending';

    public const EMBEDDING_READY = 'ready';

    public const EMBEDDING_FAILED = 'failed';

    /** Vectors are stored as packed little-endian float32. */
    private const PACK_FORMAT = 'g';

    protected $fillable = [
        'knowledge_base_id', 'knowledge_source_id', 'knowledge_collection_id',
        'knowledge_document_id', 'knowledge_website_page_id', 'knowledge_faq_id',
        'knowledge_article_id',
        'owner_key', 'chunk_index', 'content', 'content_hash', 'token_count',
        'character_count', 'language', 'metadata', 'source_type', 'priority',
        'is_retrievable', 'effective_from', 'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'source_type' => SourceType::class,
            'priority' => SourcePriority::class,
            'is_retrievable' => 'boolean',
            'embedded_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    /** Raw vector bytes must never reach a JSON response. */
    protected $hidden = ['embedding'];

    // -- Relationships -------------------------------------------------------

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    public function websitePage(): BelongsTo
    {
        return $this->belongsTo(KnowledgeWebsitePage::class, 'knowledge_website_page_id');
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(KnowledgeFaq::class, 'knowledge_faq_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'knowledge_article_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCollection::class, 'knowledge_collection_id');
    }

    // -- Scopes --------------------------------------------------------------

    public function scopeSearchable(Builder $query): Builder
    {
        return $query
            ->where('is_retrievable', true)
            ->where('embedding_status', self::EMBEDDING_READY);
    }

    /** Excludes chunks outside their effective window (expired pricing, future policies). */
    public function scopeEffective(Builder $query, ?\DateTimeInterface $moment = null): Builder
    {
        $moment ??= now();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $moment))
            ->where(fn (Builder $q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $moment));
    }

    // -- Vector helpers ------------------------------------------------------

    /**
     * Packs a float vector into the binary column format.
     *
     * @param  array<int, float>  $vector
     */
    public static function packVector(array $vector): string
    {
        return pack(self::PACK_FORMAT.'*', ...array_map('floatval', $vector));
    }

    /**
     * Unpacks binary column data back into a zero-indexed float array.
     *
     * @return array<int, float>
     */
    public static function unpackVector(?string $binary): array
    {
        if ($binary === null || $binary === '') {
            return [];
        }

        $unpacked = unpack(self::PACK_FORMAT.'*', $binary);

        return $unpacked === false ? [] : array_values($unpacked);
    }

    /** @return array<int, float> */
    public function vector(): array
    {
        return self::unpackVector($this->embedding);
    }

    /** @param  array<int, float>  $vector */
    public function setVector(array $vector, string $provider, string $model, int $version): void
    {
        $this->embedding = self::packVector($vector);
        $this->embedding_provider = $provider;
        $this->embedding_model = $model;
        $this->embedding_dimensions = count($vector);
        $this->embedding_version = $version;
        $this->embedding_status = self::EMBEDDING_READY;
        $this->embedded_at = now();
    }

    // -- Citations -----------------------------------------------------------

    /**
     * Citation payload for an answer. Reads only from the denormalised metadata
     * written at index time — if a field is absent it is omitted rather than
     * guessed, because a plausible-looking wrong page number is worse than no
     * page number.
     *
     * @return array<string, mixed>
     */
    public function citation(): array
    {
        $metadata = $this->metadata ?? [];

        return array_filter([
            'chunk_uuid' => $this->uuid,
            'source_type' => $this->source_type?->value,
            'document_title' => $metadata['document_name'] ?? null,
            'page' => $metadata['page'] ?? null,
            'section' => $metadata['heading'] ?? $metadata['section'] ?? null,
            'url' => $metadata['url'] ?? null,
            'faq_question' => $metadata['faq_question'] ?? null,
            'article_title' => $metadata['article_title'] ?? null,
            'collection' => $metadata['collection'] ?? null,
            'last_updated' => $metadata['last_updated'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
