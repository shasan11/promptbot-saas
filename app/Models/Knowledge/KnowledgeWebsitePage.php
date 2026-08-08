<?php

namespace App\Models\Knowledge;

use App\Models\Knowledge\Concerns\HasKnowledgeTags;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Crawl bookkeeping for one URL. The page's *content* becomes a
 * KnowledgeDocument; this row tracks discovery, HTTP state and the content
 * fingerprint that lets a re-crawl skip unchanged pages instead of paying to
 * re-embed them.
 */
class KnowledgeWebsitePage extends Model
{
    use HasKnowledgeTags, HasPublicUuid;

    public const STATUS_DISCOVERED = 'discovered';

    public const STATUS_FETCHED = 'fetched';

    public const STATUS_INDEXED = 'indexed';

    public const STATUS_UNCHANGED = 'unchanged';

    public const STATUS_EXCLUDED = 'excluded';

    public const STATUS_MISSING = 'missing';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'knowledge_base_id', 'knowledge_source_id', 'knowledge_document_id',
        'url', 'canonical_url', 'url_hash', 'page_title', 'meta_description',
        'content_hash', 'http_status', 'depth', 'language', 'word_count',
        'status', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_crawled_at' => 'datetime',
            'last_changed_at' => 'datetime',
            'indexed_at' => 'datetime',
            'missing_since' => 'datetime',
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

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function chunkOwnerKey(): string
    {
        return 'page:'.$this->id;
    }
}
