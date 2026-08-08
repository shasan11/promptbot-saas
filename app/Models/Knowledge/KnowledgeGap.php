<?php

namespace App\Models\Knowledge;

use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A question the knowledge base answered badly or not at all, rolled up by
 * normalised query. The point of the module's analytics: turn a retrieval
 * failure into an FAQ someone can write.
 */
class KnowledgeGap extends Model
{
    use HasPublicUuid;

    public const ORIGIN_ZERO_RESULT = 'zero_result';

    public const ORIGIN_LOW_CONFIDENCE = 'low_confidence';

    public const ORIGIN_ESCALATION = 'escalation';

    public const STATUS_OPEN = 'open';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'knowledge_base_id', 'question', 'query_hash', 'origin', 'occurrences',
        'best_score', 'first_seen_at', 'last_seen_at', 'status', 'assigned_to',
        'resolved_faq_id', 'dedupe_key',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'best_score' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $gap): void {
            $gap->dedupe_key = hash('sha256', ($gap->knowledge_base_id ?? '-').'|'.$gap->query_hash);
        });
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedFaq(): BelongsTo
    {
        return $this->belongsTo(KnowledgeFaq::class, 'resolved_faq_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ASSIGNED]);
    }
}
