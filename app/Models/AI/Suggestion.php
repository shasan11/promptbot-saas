<?php

namespace App\Models\AI;

use App\Enums\AI\SuggestionStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Suggestion extends Model
{
    use HasPublicUuid;
    protected $table = 'ai_suggestions';
    protected $guarded = ['id', 'public_uuid'];
    protected function casts(): array
    {
        return [
            'status' => SuggestionStatus::class, 'structured_payload' => 'array', 'citations' => 'array',
            'evidence' => 'array', 'confidence_basis' => 'array', 'accepted_at' => 'datetime',
            'rejected_at' => 'datetime', 'sent_at' => 'datetime',
        ];
    }
    public function run(): BelongsTo { return $this->belongsTo(Run::class, 'ai_run_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function feedback(): HasMany { return $this->hasMany(Feedback::class, 'ai_suggestion_id'); }
}
