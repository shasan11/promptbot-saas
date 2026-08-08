<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\ProcessingJobStatus;
use App\Enums\Knowledge\ProcessingStage;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The operator-visible record of one unit of async work.
 *
 * Deliberately separate from Laravel's `jobs` table: that row disappears the
 * moment a worker picks the job up, which is exactly when an operator most
 * wants to see it. This row is created at dispatch and survives completion.
 */
class KnowledgeProcessingJob extends Model
{
    use HasPublicUuid;

    public const TYPE_DOCUMENT = 'document_processing';

    public const TYPE_EMBEDDING = 'embedding';

    public const TYPE_CRAWL = 'website_crawl';

    public const TYPE_SYNC = 'source_sync';

    public const TYPE_REINDEX = 'reindex';

    public const TYPE_PURGE = 'purge';

    protected $fillable = [
        'knowledge_base_id', 'knowledge_source_id', 'knowledge_document_id',
        'job_type', 'queue', 'status', 'current_stage', 'progress',
        'items_total', 'items_processed', 'items_failed', 'attempt', 'max_attempts',
        'queued_at', 'correlation_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProcessingJobStatus::class,
            'current_stage' => ProcessingStage::class,
            'cancel_requested' => 'boolean',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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

    public function logs(): HasMany
    {
        return $this->hasMany(KnowledgeProcessingLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProcessingJobStatus::Queued->value,
            ProcessingJobStatus::Running->value,
            ProcessingJobStatus::Retrying->value,
        ]);
    }

    /**
     * True once a job has been running longer than the platform's stale
     * threshold — used to surface work whose worker died without reporting.
     */
    public function scopeStale(Builder $query): Builder
    {
        return $query
            ->where('status', ProcessingJobStatus::Running->value)
            ->where('started_at', '<', now()->subMinutes((int) config('knowledge.processing.stale_job_after_minutes')));
    }

    /**
     * Cooperative cancellation checkpoint for long-running workers. Reads
     * straight from the database rather than the in-memory model so a cancel
     * issued mid-crawl is observed on the next page.
     */
    public function isCancelled(): bool
    {
        return (bool) static::query()->whereKey($this->id)->value('cancel_requested');
    }
}
