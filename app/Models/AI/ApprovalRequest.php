<?php

namespace App\Models\AI;

use App\Enums\AI\ApprovalStatus;
use App\Enums\Connections\ActionRiskLevel;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Connections\ConnectionAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    use HasPublicUuid;
    protected $table = 'ai_approval_requests';
    protected $guarded = ['id', 'public_uuid'];
    protected $hidden = ['resume_token_encrypted'];
    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class, 'risk_level' => ActionRiskLevel::class,
            'arguments_redacted' => 'array', 'context' => 'array', 'resume_token_encrypted' => 'encrypted',
            'requested_at' => 'datetime', 'expires_at' => 'datetime', 'decided_at' => 'datetime',
        ];
    }
    public function run(): BelongsTo { return $this->belongsTo(Run::class, 'ai_run_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function action(): BelongsTo { return $this->belongsTo(ConnectionAction::class, 'connection_action_id'); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
