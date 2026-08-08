<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\RetrievalMode;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeRetrievalLog extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public const CHANNEL_PLAYGROUND = 'playground';

    public const CHANNEL_AGENT = 'agent';

    public const CHANNEL_API = 'api';

    public const CHANNEL_INBOX = 'inbox';

    protected $fillable = [
        'knowledge_base_id', 'channel', 'actor_type', 'user_id', 'agent_key',
        'query', 'query_hash', 'language', 'retrieval_mode',
        'candidates_semantic', 'candidates_keyword', 'results_returned',
        'top_score', 'average_score', 'zero_results', 'below_threshold',
        'embedding_ms', 'semantic_ms', 'keyword_ms', 'rerank_ms', 'total_ms',
        'filters', 'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'retrieval_mode' => RetrievalMode::class,
            'filters' => 'array',
            'zero_results' => 'boolean',
            'below_threshold' => 'boolean',
            'top_score' => 'float',
            'average_score' => 'float',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(KnowledgeRetrievalResult::class)->orderBy('rank');
    }

    /**
     * Normalises a query so that casing, punctuation and whitespace variants of
     * the same question aggregate into one row on the analytics reports.
     */
    public static function hashQuery(string $query): string
    {
        $normalised = preg_replace('/\s+/u', ' ', mb_strtolower(trim($query)));
        $normalised = preg_replace('/[^\p{L}\p{N} ]+/u', '', (string) $normalised);

        return hash('sha256', trim((string) $normalised));
    }

    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('zero_results', true)->orWhere('below_threshold', true));
    }
}
