<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\ProcessingStage;
use App\Models\Knowledge\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeFailure extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'knowledge_base_id', 'knowledge_source_id', 'knowledge_document_id',
        'knowledge_processing_job_id', 'stage', 'category', 'message',
        'technical_details', 'attempt', 'retryable',
    ];

    protected function casts(): array
    {
        return [
            'stage' => ProcessingStage::class,
            'category' => FailureCategory::class,
            'retryable' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Exception detail is gated behind knowledge.manage — a support agent
     * looking at the Failed Sources list gets the actionable `message`, not a
     * stack trace that may quote file paths or document content.
     */
    protected $hidden = ['technical_details'];

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

    public function job(): BelongsTo
    {
        return $this->belongsTo(KnowledgeProcessingJob::class, 'knowledge_processing_job_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }
}
