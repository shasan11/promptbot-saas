<?php

namespace App\Models\AI;

use App\Enums\AI\AgentStatus;
use App\Enums\AI\DeploymentMode;
use App\Models\Channel\Channel;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Connections\ConnectionAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $table = 'ai_agents';

    protected $fillable = [
        'agent_key', 'name', 'description', 'status', 'purpose', 'system_instructions', 'tone',
        'language_mode', 'supported_languages', 'provider_config_id', 'model', 'model_parameters',
        'deployment_mode', 'confidence_policy', 'memory_enabled', 'memory_strategy', 'memory_config',
        'max_context_tokens', 'max_tool_calls', 'max_steps', 'timeout_seconds', 'require_citations',
        'fallback_behavior', 'human_approval_mode', 'auto_reply_enabled', 'behaviors', 'guardrails',
        'limits', 'draft_version', 'deployed_version_id', 'created_by', 'updated_by', 'deployed_by', 'deployed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
            'deployment_mode' => DeploymentMode::class,
            'supported_languages' => 'array',
            'model_parameters' => 'array',
            'confidence_policy' => 'array',
            'memory_enabled' => 'boolean',
            'memory_config' => 'array',
            'require_citations' => 'boolean',
            'auto_reply_enabled' => 'boolean',
            'behaviors' => 'array',
            'guardrails' => 'array',
            'limits' => 'array',
            'deployed_at' => 'datetime',
        ];
    }

    public function providerConfig(): BelongsTo { return $this->belongsTo(ProviderConfig::class); }
    public function versions(): HasMany { return $this->hasMany(AgentVersion::class)->orderByDesc('version'); }
    public function deployedVersion(): BelongsTo { return $this->belongsTo(AgentVersion::class, 'deployed_version_id'); }
    public function runs(): HasMany { return $this->hasMany(Run::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function deployer(): BelongsTo { return $this->belongsTo(User::class, 'deployed_by'); }

    public function connectionActions(): BelongsToMany
    {
        return $this->belongsToMany(ConnectionAction::class, 'ai_agent_tools')
            ->withPivot(['enabled', 'approval_policy', 'configuration'])->withTimestamps();
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'ai_agent_channels')
            ->withPivot(['deployment_mode', 'enabled', 'configuration'])->withTimestamps();
    }

    public function isDeployed(): bool
    {
        return $this->deployed_version_id !== null && $this->status === AgentStatus::Active;
    }
}
