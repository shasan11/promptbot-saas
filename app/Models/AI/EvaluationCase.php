<?php

namespace App\Models\AI;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationCase extends Model
{
    use HasPublicUuid;
    protected $table = 'ai_evaluation_cases';
    protected $guarded = ['id', 'public_uuid'];
    protected function casts(): array { return ['expected' => 'array', 'assertions' => 'array', 'active' => 'boolean']; }
    public function suite(): BelongsTo { return $this->belongsTo(EvaluationSuite::class, 'suite_id'); }
}
