<?php

namespace App\Models\AI;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationSuite extends Model
{
    use HasPublicUuid;
    protected $table = 'ai_evaluation_suites';
    protected $guarded = ['id', 'public_uuid'];
    protected function casts(): array { return ['active' => 'boolean']; }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function cases(): HasMany { return $this->hasMany(EvaluationCase::class, 'suite_id'); }
    public function runs(): HasMany { return $this->hasMany(EvaluationRun::class, 'suite_id'); }
}
