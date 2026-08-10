<?php

namespace App\Services\AI;

use App\Enums\AI\AgentStatus;
use App\Enums\AI\DeploymentMode;
use App\Enums\AI\ProviderStatus;
use App\Models\AI\Agent;
use App\Models\AI\AgentVersion;
use App\Models\Channel\Channel;
use App\Models\Connections\ConnectionAction;
use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\SaaS\TenantFeatureService;

class AgentConfigurationService
{
    public function __construct(
        private readonly ProviderCapabilityService $capabilities,
        private readonly TenantAuditLogService $audit,
        private readonly AISettingsService $settings,
        private readonly TenantFeatureService $features,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Agent
    {
        $agent = Agent::query()->create($this->payload($data) + [
            'agent_key' => $this->uniqueKey($data['name']), 'created_by' => $actor->id, 'updated_by' => $actor->id,
        ]);
        $this->audit->record('ai.agent_created', $actor, 'Created AI agent', $agent, newValues: $this->snapshot($agent), subjectLabel: $agent->name);
        return $agent;
    }

    /** @param array<string, mixed> $data */
    public function update(Agent $agent, array $data, User $actor): Agent
    {
        $before = $this->snapshot($agent);
        $agent->fill($this->payload($data) + ['updated_by' => $actor->id, 'status' => AgentStatus::Draft]);
        $agent->increment('draft_version');
        $agent->save();
        $this->audit->record('ai.agent_updated', $actor, 'Updated AI agent draft', $agent, oldValues: $before, newValues: $this->snapshot($agent), subjectLabel: $agent->name);
        return $agent;
    }

    public function deploy(Agent $agent, User $actor): AgentVersion
    {
        $provider = $agent->providerConfig;
        if (! $provider || ! $provider->enabled || $provider->status !== ProviderStatus::Healthy) {
            throw ValidationException::withMessages(['provider_config_id' => 'The agent needs an enabled, healthy provider before deployment.']);
        }
        $required = ['chat'];
        if ($agent->connectionActions()->wherePivot('enabled', true)->exists()) $required[] = 'tool_calling';
        $this->capabilities->ensure($provider, $required);
        if ($agent->deployment_mode === DeploymentMode::Autonomous && ! (bool) config('ai.safety.autonomous_replies_enabled')) {
            throw ValidationException::withMessages(['deployment_mode' => 'Autonomous replies are disabled by platform safety policy.']);
        }
        if ($agent->deployment_mode === DeploymentMode::Autonomous && (! $agent->auto_reply_enabled || ! $this->features->enabled('ai_autonomous_replies') || ! $this->settings->current()['autonomous_replies_enabled'])) {
            throw ValidationException::withMessages(['deployment_mode' => 'Enable autonomous replies for the Agent, workspace, and plan before deploying autonomous mode.']);
        }

        return DB::transaction(function () use ($agent, $actor): AgentVersion {
            $version = AgentVersion::query()->create([
                'agent_id' => $agent->id, 'version' => $agent->versions()->max('version') + 1,
                'configuration_snapshot' => $this->snapshot($agent) + ['channels' => $agent->channels()->get()->map(fn ($channel) => [
                    'public_uuid' => $channel->public_uuid, 'deployment_mode' => $channel->pivot->deployment_mode,
                    'enabled' => (bool) $channel->pivot->enabled,
                ])->all()],
                'knowledge_snapshot' => ['agent_key' => $agent->agent_key],
                'tool_policy_snapshot' => $agent->connectionActions()->get()->map(fn ($action) => [
                    'action_uuid' => $action->uuid, 'key' => $action->key, 'risk' => $action->risk_level?->value,
                    'approval_policy' => $action->pivot->approval_policy,
                ])->all(),
                'created_by' => $actor->id,
            ]);
            $agent->forceFill(['deployed_version_id' => $version->id, 'status' => AgentStatus::Active, 'deployed_by' => $actor->id, 'deployed_at' => now()])->save();
            $this->audit->record('ai.agent_deployed', $actor, "Deployed AI agent version {$version->version}", $agent, newValues: ['version' => $version->version], subjectLabel: $agent->name);
            return $version;
        });
    }

    public function pause(Agent $agent, User $actor): void
    {
        $agent->forceFill(['status' => AgentStatus::Paused, 'updated_by' => $actor->id])->save();
        $this->audit->record('ai.agent_paused', $actor, 'Paused AI agent', $agent, subjectLabel: $agent->name);
    }

    public function restore(Agent $agent, AgentVersion $version, User $actor): void
    {
        if ($version->agent_id !== $agent->id) abort(404);
        DB::transaction(function () use ($agent, $version, $actor): void {
            $before = $this->snapshot($agent);
            $snapshot = (array) $version->configuration_snapshot;
            $agent->fill(collect($snapshot)->except(['agent_key','channels'])->all());
            $agent->forceFill(['status' => AgentStatus::Draft, 'updated_by' => $actor->id,
                'draft_version' => $agent->draft_version + 1])->save();

            $channelIds = Channel::query()->whereIn('public_uuid', collect($snapshot['channels'] ?? [])->pluck('public_uuid'))->pluck('id', 'public_uuid');
            $agent->channels()->sync(collect($snapshot['channels'] ?? [])->mapWithKeys(fn (array $channel) => isset($channelIds[$channel['public_uuid']])
                ? [$channelIds[$channel['public_uuid']] => ['enabled' => (bool) ($channel['enabled'] ?? true), 'deployment_mode' => $channel['deployment_mode'] ?? $agent->deployment_mode->value]] : [])->all());

            $toolIds = ConnectionAction::query()->whereIn('uuid', collect($version->tool_policy_snapshot ?? [])->pluck('action_uuid'))->pluck('id', 'uuid');
            $agent->connectionActions()->sync(collect($version->tool_policy_snapshot ?? [])->mapWithKeys(fn (array $tool) => isset($toolIds[$tool['action_uuid']])
                ? [$toolIds[$tool['action_uuid']] => ['enabled' => true, 'approval_policy' => $tool['approval_policy'] ?? 'inherit']] : [])->all());
            $this->audit->record('ai.agent_version_restored', $actor, "Restored agent version {$version->version} into a new draft", $agent,
                oldValues: $before, newValues: $this->snapshot($agent), subjectLabel: $agent->name,
                metadata: ['source_version_uuid' => $version->public_uuid]);
        });
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return [
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'purpose' => $data['purpose'] ?? null,
            'system_instructions' => $data['system_instructions'], 'provider_config_id' => $data['provider_config_id'],
            'model' => $data['model'] ?? null, 'model_parameters' => ['temperature' => (float) $data['temperature'], 'max_tokens' => (int) $data['max_tokens'], 'reasoning_effort' => $data['reasoning_effort']],
            'deployment_mode' => $data['deployment_mode'], 'require_citations' => (bool) $data['require_citations'],
            'human_approval_mode' => $data['human_approval_mode'],
            'auto_reply_enabled' => $data['deployment_mode'] === 'autonomous' && (bool) $data['auto_reply_enabled'],
            'max_context_tokens' => min((int) config('ai.runtime.max_context_tokens'), (int) $data['max_context_tokens']),
            'max_tool_calls' => min((int) config('ai.runtime.max_tool_calls'), (int) $data['max_tool_calls']),
            'max_steps' => min((int) config('ai.runtime.max_steps'), (int) $data['max_steps']),
            'timeout_seconds' => min((int) config('ai.runtime.maximum_timeout_seconds'), (int) $data['timeout_seconds']),
            'fallback_behavior' => 'human_handoff',
            'memory_enabled' => (bool) $data['memory_enabled'], 'memory_strategy' => $data['memory_strategy'],
        ];
    }

    /** @return array<string, mixed> */
    public function snapshot(Agent $agent): array
    {
        return collect($agent->only(['agent_key','name','description','purpose','system_instructions','provider_config_id','model','model_parameters','deployment_mode','require_citations','human_approval_mode','auto_reply_enabled','memory_enabled','memory_strategy','max_context_tokens','max_tool_calls','max_steps','timeout_seconds','fallback_behavior']))->all();
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'agent'; $key = $base; $suffix = 2;
        while (Agent::withTrashed()->where('agent_key', $key)->exists()) $key = $base.'_'.$suffix++;
        return $key;
    }
}
