<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationResult extends Model
{
    protected $table = 'ai_evaluation_results';
    protected $guarded = ['id'];
    protected function casts(): array { return ['assertion_results' => 'array']; }
    public function evaluationRun(): BelongsTo { return $this->belongsTo(EvaluationRun::class); }
    public function evaluationCase(): BelongsTo { return $this->belongsTo(EvaluationCase::class); }
    public function aiRun(): BelongsTo { return $this->belongsTo(Run::class, 'ai_run_id'); }
}
