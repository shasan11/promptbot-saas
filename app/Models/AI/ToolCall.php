<?php

namespace App\Models\AI;

use App\Enums\Connections\ActionRiskLevel;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Connections\ConnectionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolCall extends Model
{
    use HasPublicUuid;
    protected $table = 'ai_tool_calls';
    protected $guarded = ['id', 'public_uuid'];
    protected function casts(): array { return ['risk_level' => ActionRiskLevel::class, 'arguments_redacted' => 'array', 'requires_approval' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime']; }
    public function run(): BelongsTo { return $this->belongsTo(Run::class, 'ai_run_id'); }
    public function action(): BelongsTo { return $this->belongsTo(ConnectionAction::class, 'connection_action_id'); }
    public function approval(): BelongsTo { return $this->belongsTo(ApprovalRequest::class, 'approval_request_id'); }
}
