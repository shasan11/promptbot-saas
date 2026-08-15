<?php

namespace App\Models\AI;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationRun extends Model
{
    use HasPublicUuid;
    protected $table = 'ai_evaluation_runs';
    protected $guarded = ['id', 'public_uuid'];
    protected function casts(): array { return ['pass_rate' => 'decimal:3', 'started_at' => 'datetime', 'finished_at' => 'datetime']; }
    public function suite(): BelongsTo { return $this->belongsTo(EvaluationSuite::class, 'suite_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function results(): HasMany { return $this->hasMany(EvaluationResult::class, 'evaluation_run_id'); }
}
