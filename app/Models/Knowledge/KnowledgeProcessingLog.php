<?php

namespace App\Models\Knowledge;

use App\Enums\Knowledge\ProcessingStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeProcessingLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'knowledge_processing_job_id', 'knowledge_source_id', 'knowledge_document_id',
        'stage', 'level', 'message', 'context', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'stage' => ProcessingStage::class,
            'context' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(KnowledgeProcessingJob::class, 'knowledge_processing_job_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }
}
