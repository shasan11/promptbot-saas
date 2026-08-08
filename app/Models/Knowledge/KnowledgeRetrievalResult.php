<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeRetrievalResult extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'knowledge_retrieval_log_id', 'knowledge_chunk_id', 'rank',
        'semantic_score', 'keyword_score', 'final_score',
        'included_in_context', 'exclusion_reason',
    ];

    protected function casts(): array
    {
        return [
            'semantic_score' => 'float',
            'keyword_score' => 'float',
            'final_score' => 'float',
            'included_in_context' => 'boolean',
        ];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(KnowledgeRetrievalLog::class, 'knowledge_retrieval_log_id');
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class, 'knowledge_chunk_id');
    }
}
