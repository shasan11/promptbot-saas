<?php

namespace App\Models\AI;

use App\Enums\AI\ErrorCategory;
use App\Enums\AI\RunStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Inbox\Conversation;
use App\Models\Inbox\Message;
use App\Models\Ticket\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Run extends Model
{
    use HasPublicUuid;
    protected $table = 'ai_runs';
    protected $guarded = ['id', 'public_uuid'];
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class, 'error_category' => ErrorCategory::class,
            'started_at' => 'datetime', 'finished_at' => 'datetime', 'estimated_cost' => 'decimal:8', 'metadata' => 'array',
        ];
    }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function agentVersion(): BelongsTo { return $this->belongsTo(AgentVersion::class); }
    public function providerConfig(): BelongsTo { return $this->belongsTo(ProviderConfig::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function message(): BelongsTo { return $this->belongsTo(Message::class); }
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function usageLogs(): HasMany { return $this->hasMany(UsageLog::class, 'ai_run_id'); }
    public function suggestions(): HasMany { return $this->hasMany(Suggestion::class, 'ai_run_id'); }
    public function toolCalls(): HasMany { return $this->hasMany(ToolCall::class, 'ai_run_id'); }
    public function approvals(): HasMany { return $this->hasMany(ApprovalRequest::class, 'ai_run_id'); }
}
