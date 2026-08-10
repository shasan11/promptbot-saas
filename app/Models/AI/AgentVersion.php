<?php

namespace App\Models\AI;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentVersion extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;
    protected $table = 'ai_agent_versions';
    protected $fillable = ['agent_id', 'version', 'configuration_snapshot', 'prompt_snapshot', 'knowledge_snapshot', 'tool_policy_snapshot', 'created_by'];
    protected function casts(): array { return ['configuration_snapshot' => 'array', 'prompt_snapshot' => 'array', 'knowledge_snapshot' => 'array', 'tool_policy_snapshot' => 'array']; }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
