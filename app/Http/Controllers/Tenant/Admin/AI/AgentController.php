<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AI\SaveAgentRequest;
use App\Models\AI\Agent;
use App\Models\AI\AgentVersion;
use App\Models\AI\ProviderConfig;
use App\Models\Connections\ConnectionAction;
use App\Models\Channel\Channel;
use App\Services\Tenant\TenantAuditLogService;
use App\Services\AI\AgentConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('ai.agents.view'), 403);
        return Inertia::render('Tenant/Admin/AI/Agents/Index', [
            'agents' => Agent::query()->with(['providerConfig:id,public_uuid,name,status', 'deployedVersion:id,public_uuid,version', 'versions.creator:id,name', 'connectionActions:id,public_uuid,connection_id,key,name,risk_level', 'channels:id,public_uuid,name,type'])->latest()->get()->map(fn (Agent $agent) => $this->payload($agent)),
            'providers' => ProviderConfig::query()->where('enabled', true)->get()->map(fn (ProviderConfig $provider) => ['id' => $provider->id, 'public_uuid' => $provider->public_uuid, 'name' => $provider->name, 'status' => $provider->status->value, 'default_chat_model' => $provider->default_chat_model, 'capabilities' => $provider->capabilities ?? []]),
            'canManage' => $request->user('tenant')->can('ai.agents.manage'),
            'canDeploy' => $request->user('tenant')->can('ai.agents.deploy'),
            'tools' => ConnectionAction::query()->with('connection:id,name')->where('enabled_for_ai', true)->where('status', 'active')->orderBy('name')->get()->map(fn (ConnectionAction $action) => ['id' => $action->id, 'name' => $action->name, 'key' => $action->key, 'connection' => $action->connection?->name, 'risk_level' => $action->risk_level?->value, 'requires_approval' => $action->requires_approval]),
            'channels' => Channel::query()->where('status', 'active')->orderBy('name')->get(['id','public_uuid','name','type']),
            'limits' => ['max_context_tokens' => config('ai.runtime.max_context_tokens'), 'max_tool_calls' => config('ai.runtime.max_tool_calls'), 'max_steps' => config('ai.runtime.max_steps'), 'max_timeout' => config('ai.runtime.maximum_timeout_seconds')],
        ]);
    }

    public function store(SaveAgentRequest $request, AgentConfigurationService $service): RedirectResponse
    {
        $service->create($request->validated(), $request->user('tenant'));
        return back()->with('status', 'AI agent draft created.');
    }

    public function update(SaveAgentRequest $request, Agent $agent, AgentConfigurationService $service): RedirectResponse
    {
        $service->update($agent, $request->validated(), $request->user('tenant'));
        return back()->with('status', 'AI agent draft updated. Deploy it when ready.');
    }

    public function deploy(Request $request, Agent $agent, AgentConfigurationService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.agents.deploy'), 403);
        $version = $service->deploy($agent, $request->user('tenant'));
        return back()->with('status', "Agent version {$version->version} deployed.");
    }

    public function pause(Request $request, Agent $agent, AgentConfigurationService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.agents.deploy'), 403);
        $service->pause($agent, $request->user('tenant'));
        return back()->with('status', 'AI agent paused.');
    }

    public function restore(Request $request, Agent $agent, AgentVersion $version, AgentConfigurationService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.agents.manage'), 403);
        $service->restore($agent, $version, $request->user('tenant'));
        return back()->with('status', "Agent version {$version->version} restored into a draft. Review and deploy it explicitly.");
    }

    public function updateTools(Request $request, Agent $agent, TenantAuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.agents.manage'), 403);
        $data = $request->validate(['action_ids' => ['array','max:50'], 'action_ids.*' => ['integer','distinct','exists:connection_actions,id']]);
        $allowed = ConnectionAction::query()->whereIn('id', $data['action_ids'] ?? [])->where('enabled_for_ai', true)->pluck('id');
        $agent->connectionActions()->sync($allowed->mapWithKeys(fn ($id) => [$id => ['enabled' => true, 'approval_policy' => 'inherit']])->all());
        $agent->forceFill(['status' => 'draft', 'updated_by' => $request->user('tenant')->id])->save();
        $audit->record('ai.agent_tools_updated', $request->user('tenant'), 'Updated AI agent tools', $agent, newValues: ['action_ids' => $allowed->all()], subjectLabel: $agent->name);
        return back()->with('status', 'Agent tools updated. Redeploy the agent to activate the new tool policy.');
    }

    public function updateChannels(Request $request, Agent $agent, TenantAuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.agents.manage'), 403);
        $data = $request->validate(['channel_ids' => ['array','max:50'], 'channel_ids.*' => ['integer','distinct','exists:channels,id']]);
        $agent->channels()->sync(collect($data['channel_ids'] ?? [])->mapWithKeys(fn ($id) => [$id => ['enabled' => true, 'deployment_mode' => $agent->deployment_mode->value]])->all());
        $agent->forceFill(['status' => 'draft', 'updated_by' => $request->user('tenant')->id])->save();
        $audit->record('ai.agent_channels_updated', $request->user('tenant'), 'Updated AI agent channel deployment', $agent, newValues: ['channel_ids' => $data['channel_ids'] ?? []], subjectLabel: $agent->name);
        return back()->with('status', 'Agent channels updated. Redeploy the agent to activate the change.');
    }

    private function payload(Agent $agent): array
    {
        return [
            'public_uuid' => $agent->public_uuid, 'agent_key' => $agent->agent_key, 'name' => $agent->name,
            'description' => $agent->description, 'purpose' => $agent->purpose, 'status' => $agent->status->value,
            'system_instructions' => $agent->system_instructions, 'provider_config_id' => $agent->provider_config_id,
            'provider' => $agent->providerConfig?->name, 'provider_status' => $agent->providerConfig?->status->value,
            'model' => $agent->model, 'model_parameters' => $agent->model_parameters,
            'deployment_mode' => $agent->deployment_mode->value, 'require_citations' => $agent->require_citations,
            'human_approval_mode' => $agent->human_approval_mode, 'max_context_tokens' => $agent->max_context_tokens,
            'auto_reply_enabled' => $agent->auto_reply_enabled,
            'memory_enabled' => $agent->memory_enabled, 'memory_strategy' => $agent->memory_strategy,
            'max_tool_calls' => $agent->max_tool_calls, 'max_steps' => $agent->max_steps, 'timeout_seconds' => $agent->timeout_seconds,
            'deployed_version' => $agent->deployedVersion?->version, 'deployed_at' => $agent->deployed_at,
            'tool_ids' => $agent->connectionActions->pluck('id')->all(),
            'channel_ids' => $agent->channels->pluck('id')->all(),
            'versions' => $agent->versions->map(fn (AgentVersion $version) => [
                'public_uuid' => $version->public_uuid, 'version' => $version->version,
                'configuration' => $version->configuration_snapshot, 'tools' => $version->tool_policy_snapshot ?? [],
                'created_by' => $version->creator?->name, 'created_at' => $version->created_at,
                'deployed' => $agent->deployed_version_id === $version->id,
            ])->values(),
        ];
    }
}
