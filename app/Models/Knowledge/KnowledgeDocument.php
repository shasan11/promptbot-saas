<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\ProcessingStage;
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
 * One ingestible item of knowledge.
 *
 * Three kinds share this table because they share the entire downstream
 * pipeline — extract, normalise, chunk, embed, index — and all three need
 * versioning, tagging and citation metadata:
 *
 *   file          an uploaded PDF/DOCX/… with bytes in object storage
 *   manual_text   an article written in PromptBot; no stored file
 *   website_page  the extracted body of one crawled URL
 */
class KnowledgeDocument extends Model
{
    use HasKnowledgeTags, HasPublicUuid, SoftDeletes;

    public const KIND_FILE = 'file';

    public const KIND_MANUAL_TEXT = 'manual_text';

    public const KIND_WEBSITE_PAGE = 'website_page';

    protected $fillable = [
        'knowledge_base_id', 'knowledge_source_id', 'knowledge_collection_id',
        'title', 'kind', 'original_filename', 'storage_disk', 'storage_path',
        'mime_type', 'extension', 'file_size', 'checksum',
        'extracted_text', 'content_hash', 'structure', 'language',
        'effective_from', 'effective_until', 'review_due_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'current_stage' => ProcessingStage::class,
            'failure_category' => FailureCategory::class,
            'structure' => 'array',
            'ocr_applied' => 'boolean',
            'has_tables' => 'boolean',
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
            'indexed_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'review_due_at' => 'datetime',
        ];
    }

    /**
     * Extracted text can be megabytes and is wanted only by the preview screen
     * and the chunker. Hiding it keeps it out of Inertia payloads and API
     * resources even when a caller passes a whole model through. Note this is a
     * *serialisation* guard, not a query one — list queries must still restrict
     * their own columns; scopeSummaryColumns() below is the shorthand for that.
     */
    protected $hidden = ['extracted_text'];

    // -- Relationships -------------------------------------------------------

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
        return $this->hasMany(KnowledgeChunk::class)->orderBy('chunk_index');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeDocumentVersion::class)->orderByDesc('version_number');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocumentVersion::class, 'active_version_id');
    }

    public function websitePage(): HasOne
    {
        return $this->hasOne(KnowledgeWebsitePage::class, 'knowledge_document_id');
    }

    public function processingLogs(): HasMany
    {
        return $this->hasMany(KnowledgeProcessingLog::class)->latest();
    }

    public function failures(): HasMany
    {
        return $this->hasMany(KnowledgeFailure::class)->latest();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -- Scopes --------------------------------------------------------------

    /**
     * Every column except `extracted_text`. Used by the document list, source
     * detail and processing screens, none of which render the body — selecting
     * it there would pull the largest column in the schema across the wire for
     * 50 rows at a time.
     */
    public function scopeSummaryColumns(Builder $query): Builder
    {
        return $query->select([
            'id', 'uuid', 'knowledge_base_id', 'knowledge_source_id', 'knowledge_collection_id',
            'title', 'kind', 'original_filename', 'storage_disk', 'storage_path', 'mime_type',
            'extension', 'file_size', 'checksum', 'content_hash', 'language',
            'character_count', 'word_count', 'page_count', 'chunk_count', 'token_estimate',
            'ocr_applied', 'has_tables', 'status', 'current_stage',
            'processing_started_at', 'processing_completed_at', 'processing_duration_ms',
            'last_error', 'failure_stage', 'failure_category', 'retry_count', 'indexed_at',
            'version_number', 'active_version_id', 'effective_from', 'effective_until',
            'review_due_at', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at',
        ]);
    }

    public function scopeRetrievable(Builder $query): Builder
    {
        return $query->whereIn('status', [DocumentStatus::Ready->value, DocumentStatus::PartiallyReady->value]);
    }

    public function scopeInFlight(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (DocumentStatus $s) => $s->value,
            array_filter(DocumentStatus::cases(), fn (DocumentStatus $s) => $s->isInFlight())
        ));
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Failed->value);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('title', 'like', "%{$term}%")
                ->orWhere('original_filename', 'like', "%{$term}%")
                ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', "%{$term}%"));
        });
    }

    // -- Behaviour -----------------------------------------------------------

    public function hasStoredFile(): bool
    {
        return $this->kind === self::KIND_FILE && filled($this->storage_path);
    }

    public function isEffectiveAt(?\DateTimeInterface $moment = null): bool
    {
        $moment ??= now();

        return ! ($this->effective_from && $this->effective_from->gt($moment))
            && ! ($this->effective_until && $this->effective_until->lt($moment));
    }

    /** Stable owner key used to make chunk upserts idempotent. */
    public function chunkOwnerKey(): string
    {
        return 'document:'.$this->id;
    }
}
