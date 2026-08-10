<?php

namespace App\Models\AI;

use App\Models\Inbox\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationInsight extends Model
{
    protected $table = 'ai_conversation_insights';
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['suggested_tags' => 'array', 'risk_flags' => 'array', 'summary_generated_at' => 'datetime', 'classified_at' => 'datetime'];
    }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function run(): BelongsTo { return $this->belongsTo(Run::class, 'ai_run_id'); }
}
