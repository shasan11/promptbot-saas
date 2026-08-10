<?php

namespace App\Models\AI;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasPublicUuid;
    protected $table = 'ai_feedback';
    protected $guarded = ['id', 'public_uuid'];
    public function run(): BelongsTo { return $this->belongsTo(Run::class, 'ai_run_id'); }
    public function suggestion(): BelongsTo { return $this->belongsTo(Suggestion::class, 'ai_suggestion_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
}
