import AIShell from "@/Components/AI/AIShell";
import Badge from "@/Components/UI/Badge";
import Button from "@/Components/UI/Button";
import { SectionCard } from "@/Components/UI/Card";
import Input from "@/Components/UI/Input";
import Select from "@/Components/UI/Select";
import Switch from "@/Components/UI/Switch";
import Textarea from "@/Components/UI/Textarea";
import { router, useForm } from "@inertiajs/react";
import { Pause, Play, Plus } from "lucide-react";
import { useState } from "react";

const defaults = {
    name: "",
    description: "",
    purpose: "",
    system_instructions:
        "You are a careful support assistant. Help the user using only verified workspace context. Be concise, professional, and transparent when information is missing.",
    provider_config_id: "",
    model: "",
    temperature: 0.2,
    max_tokens: 1200,
    reasoning_effort: "off",
    deployment_mode: "copilot",
    require_citations: true,
    human_approval_mode: "risk_based",
    auto_reply_enabled: false,
    memory_enabled: true,
    memory_strategy: "recent_with_summary",
    max_context_tokens: 8000,
    max_tool_calls: 3,
    max_steps: 8,
    timeout_seconds: 45,
};

function ToolPicker({ agent, tools }) {
    const picker = useForm({ action_ids: agent.tool_ids || [] });
    const toggle = (id) =>
        picker.setData(
            "action_ids",
            picker.data.action_ids.includes(id)
                ? picker.data.action_ids.filter((value) => value !== id)
                : [...picker.data.action_ids, id],
        );
    return (
        <div className="mt-4 border-t border-slate-100 pt-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Connection tools
            </p>
            <div className="mt-2 grid gap-2 sm:grid-cols-2">
                {tools.map((tool) => (
                    <label
                        key={tool.id}
                        className="flex cursor-pointer items-start gap-2 rounded-md border border-slate-200 p-2 text-xs"
                    >
                        <input
                            className="mt-0.5 rounded border-slate-300"
                            type="checkbox"
                            checked={picker.data.action_ids.includes(tool.id)}
                            onChange={() => toggle(tool.id)}
                        />
                        <span>
                            <span className="font-semibold text-slate-700">
                                {tool.name}
                            </span>
                            <span className="block text-slate-400">
                                {tool.connection} · {tool.risk_level} risk
                                {tool.requires_approval ? " · approval" : ""}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            {tools.length ? (
                <Button
                    className="mt-3"
                    size="sm"
                    variant="secondary"
                    loading={picker.processing}
                    onClick={() =>
                        picker.put(
                            route(
                                "tenant.admin.ai.agents.tools.update",
                                agent.public_uuid,
                            ),
                            { preserveScroll: true },
                        )
                    }
                >
                    Save tools
                </Button>
            ) : (
                <p className="mt-2 text-xs text-slate-400">
                    No connection actions are enabled for AI. Configure them in
                    Connections first.
                </p>
            )}
            <p className="mt-2 text-[11px] text-slate-400">
                The connection must also grant this agent key ({agent.agent_key}
                ) access. High-risk actions always require human approval.
            </p>
        </div>
    );
}

function ChannelPicker({ agent, channels }) {
    const picker = useForm({ channel_ids: agent.channel_ids || [] });
    const toggle = (id) =>
        picker.setData(
            "channel_ids",
            picker.data.channel_ids.includes(id)
                ? picker.data.channel_ids.filter((value) => value !== id)
                : [...picker.data.channel_ids, id],
        );
    return (
        <div className="mt-4 border-t border-slate-100 pt-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Inbox channels
            </p>
            <div className="mt-2 flex flex-wrap gap-2">
                {channels.map((channel) => (
                    <label
                        key={channel.id}
                        className="flex cursor-pointer items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs"
                    >
                        <input
                            type="checkbox"
                            checked={picker.data.channel_ids.includes(
                                channel.id,
                            )}
                            onChange={() => toggle(channel.id)}
                        />
                        {channel.name} · {channel.type}
                    </label>
                ))}
            </div>
            {channels.length > 0 && (
                <Button
                    className="mt-3"
                    size="sm"
                    variant="secondary"
                    loading={picker.processing}
                    onClick={() =>
                        picker.put(
                            route(
                                "tenant.admin.ai.agents.channels.update",
                                agent.public_uuid,
                            ),
                            { preserveScroll: true },
                        )
                    }
                >
                    Save channels
                </Button>
            )}
        </div>
    );
}

function VersionHistory({ agent, canManage }) {
    const [left, setLeft] = useState(
        agent.versions?.[1]?.public_uuid ||
            agent.versions?.[0]?.public_uuid ||
            "",
    );
    const [right, setRight] = useState(agent.versions?.[0]?.public_uuid || "");
    if (!agent.versions?.length) return null;
    const a = agent.versions.find((version) => version.public_uuid === left);
    const b = agent.versions.find((version) => version.public_uuid === right);
    const keys =
        a && b
            ? Array.from(
                  new Set([
                      ...Object.keys(a.configuration || {}),
                      ...Object.keys(b.configuration || {}),
                  ]),
              ).filter(
                  (key) =>
                      JSON.stringify(a.configuration?.[key]) !==
                      JSON.stringify(b.configuration?.[key]),
              )
            : [];
    return (
        <div className="mt-4 border-t border-slate-100 pt-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Version history
            </p>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <Select
                    value={left}
                    onChange={(event) => setLeft(event.target.value)}
                >
                    {agent.versions.map((version) => (
                        <option
                            key={version.public_uuid}
                            value={version.public_uuid}
                        >
                            Version {version.version}
                            {version.deployed ? " · deployed" : ""}
                        </option>
                    ))}
                </Select>
                <Select
                    value={right}
                    onChange={(event) => setRight(event.target.value)}
                >
                    {agent.versions.map((version) => (
                        <option
                            key={version.public_uuid}
                            value={version.public_uuid}
                        >
                            Version {version.version}
                            {version.deployed ? " · deployed" : ""}
                        </option>
                    ))}
                </Select>
            </div>
            <p className="mt-2 text-xs text-slate-500">
                Changed fields: {keys.length ? keys.join(", ") : "none"}
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
                {agent.versions.map((version) => (
                    <div
                        key={version.public_uuid}
                        className="rounded-md border border-slate-200 px-3 py-2 text-xs"
                    >
                        <span className="font-semibold">
                            v{version.version}
                        </span>
                        {version.deployed && (
                            <span className="ml-1 text-emerald-600">
                                deployed
                            </span>
                        )}
                        <span className="ml-2 text-slate-400">
                            {version.created_by || "system"}
                        </span>
                        {canManage && !version.deployed && (
                            <Button
                                className="ml-2"
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    window.confirm(
                                        `Restore version ${version.version} into the draft?`,
                                    ) &&
                                    router.post(
                                        route(
                                            "tenant.admin.ai.agents.versions.restore",
                                            [
                                                agent.public_uuid,
                                                version.public_uuid,
                                            ],
                                        ),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Restore
                            </Button>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function Agents({
    agents,
    providers,
    canManage,
    canDeploy,
    limits,
    tools,
    channels,
}) {
    const [editing, setEditing] = useState(null);
    const form = useForm(defaults);
    const selectedProvider = providers.find(
        (provider) =>
            Number(provider.id) === Number(form.data.provider_config_id),
    );
    const reset = () => {
        setEditing(null);
        form.setData(defaults);
        form.clearErrors();
    };
    const edit = (agent) => {
        setEditing(agent.public_uuid);
        form.setData({
            ...defaults,
            ...agent,
            temperature: agent.model_parameters?.temperature ?? 0.2,
            max_tokens: agent.model_parameters?.max_tokens ?? 1200,
            reasoning_effort: agent.model_parameters?.reasoning_effort ?? "off",
        });
    };
    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        editing
            ? form.put(route("tenant.admin.ai.agents.update", editing), options)
            : form.post(route("tenant.admin.ai.agents.store"), options);
    };
    return (
        <AIShell
            title="AI agents"
            description="Create versioned tenant agents. Editing returns an agent to draft; deployment is an explicit, audited action."
            actions={
                editing && (
                    <Button variant="secondary" icon={Plus} onClick={reset}>
                        New agent
                    </Button>
                )
            }
        >
            {canManage && (
                <SectionCard
                    title={editing ? "Edit agent draft" : "Create agent draft"}
                >
                    <form className="space-y-5" onSubmit={submit}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="text-sm font-medium text-slate-700">
                                Name
                                <Input
                                    className="mt-1"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData("name", e.target.value)
                                    }
                                    required
                                />
                            </label>
                            <label className="text-sm font-medium text-slate-700">
                                Purpose
                                <Input
                                    className="mt-1"
                                    value={form.data.purpose || ""}
                                    onChange={(e) =>
                                        form.setData("purpose", e.target.value)
                                    }
                                    placeholder="Inbox support copilot"
                                />
                            </label>
                            <label className="text-sm font-medium text-slate-700">
                                Provider
                                <Select
                                    className="mt-1"
                                    value={form.data.provider_config_id}
                                    onChange={(e) =>
                                        form.setData(
                                            "provider_config_id",
                                            Number(e.target.value),
                                        )
                                    }
                                    required
                                >
                                    <option value="">Choose a provider</option>
                                    {providers.map((provider) => (
                                        <option
                                            key={provider.id}
                                            value={provider.id}
                                        >
                                            {provider.name} ({provider.status})
                                        </option>
                                    ))}
                                </Select>
                            </label>
                            <label className="text-sm font-medium text-slate-700">
                                Model override
                                <Input
                                    className="mt-1"
                                    value={form.data.model || ""}
                                    onChange={(e) =>
                                        form.setData("model", e.target.value)
                                    }
                                    placeholder="Use provider default"
                                />
                            </label>
                            <label className="text-sm font-medium text-slate-700 sm:col-span-2">
                                Description
                                <Input
                                    className="mt-1"
                                    value={form.data.description || ""}
                                    onChange={(e) =>
                                        form.setData(
                                            "description",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label className="text-sm font-medium text-slate-700 sm:col-span-2">
                                System instructions
                                <Textarea
                                    className="mt-1 min-h-32"
                                    value={form.data.system_instructions}
                                    onChange={(e) =>
                                        form.setData(
                                            "system_instructions",
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </label>
                            <label className="text-sm font-medium text-slate-700">
                                Deployment mode
                                <Select
                                    className="mt-1"
                                    value={form.data.deployment_mode}
                                    onChange={(e) =>
                                        form.setData(
                                            "deployment_mode",
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="copilot">Copilot</option>
                                    <option value="draft_only">
                                        Draft only
                                    </option>
                                    <option value="autonomous">
                                        Autonomous (platform-gated)
                                    </option>
                                </Select>
                            </label>
                            <label className="text-sm font-medium text-slate-700">
                                Approval policy
                                <Select
                                    className="mt-1"
                                    value={form.data.human_approval_mode}
                                    onChange={(e) =>
                                        form.setData(
                                            "human_approval_mode",
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="always">Always ask</option>
                                    <option value="risk_based">
                                        Risk based
                                    </option>
                                    <option value="never">
                                        Never (platform policy still applies)
                                    </option>
                                </Select>
                            </label>
                            <label className="text-sm font-medium text-slate-700">
                                Temperature
                                <Input
                                    className="mt-1"
                                    type="number"
                                    min="0"
                                    max="1.5"
                                    step="0.1"
                                    value={form.data.temperature}
                                    onChange={(e) =>
                                        form.setData(
                                            "temperature",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label className="text-sm font-medium text-slate-700">
                                Output tokens
                                <Input
                                    className="mt-1"
                                    type="number"
                                    min="64"
                                    max="8192"
                                    value={form.data.max_tokens}
                                    onChange={(e) =>
                                        form.setData(
                                            "max_tokens",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            {selectedProvider?.capabilities?.includes(
                                "reasoning",
                            ) && (
                                <label className="text-sm font-medium text-slate-700">
                                    Reasoning effort
                                    <Select
                                        className="mt-1"
                                        value={form.data.reasoning_effort}
                                        onChange={(e) =>
                                            form.setData(
                                                "reasoning_effort",
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="off">
                                            Provider default / off
                                        </option>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                    </Select>
                                </label>
                            )}
                            <label className="text-sm font-medium text-slate-700">
                                Context tokens
                                <Input
                                    className="mt-1"
                                    type="number"
                                    min="1000"
                                    max={limits.max_context_tokens}
                                    value={form.data.max_context_tokens}
                                    onChange={(e) =>
                                        form.setData(
                                            "max_context_tokens",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label className="text-sm font-medium text-slate-700">
                                Tool calls per run
                                <Input
                                    className="mt-1"
                                    type="number"
                                    min="0"
                                    max={limits.max_tool_calls}
                                    value={form.data.max_tool_calls}
                                    onChange={(e) =>
                                        form.setData(
                                            "max_tool_calls",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                        </div>
                        <Switch
                            checked={form.data.require_citations}
                            onChange={(value) =>
                                form.setData("require_citations", value)
                            }
                            label="Require grounded citations"
                            description="If no permitted knowledge is found, the agent returns an insufficient-information response without calling the model."
                        />
                        <Switch
                            checked={form.data.memory_enabled}
                            onChange={(value) =>
                                form.setData("memory_enabled", value)
                            }
                            label="Conversation context"
                            description="Inbox runs may include a bounded recent transcript and rolling summary. No cross-customer memory is created."
                        />
                        {form.data.deployment_mode === "autonomous" && (
                            <Switch
                                checked={form.data.auto_reply_enabled}
                                onChange={(value) =>
                                    form.setData("auto_reply_enabled", value)
                                }
                                label="Allow autonomous customer replies"
                                description="Still requires platform, plan, workspace, channel, grounding, guardrail, provider, and budget checks before any send."
                            />
                        )}
                        {Object.keys(form.errors).length > 0 && (
                            <p className="text-sm text-rose-600">
                                {Object.values(form.errors)[0]}
                            </p>
                        )}
                        <Button type="submit" loading={form.processing}>
                            {editing ? "Save draft" : "Create draft"}
                        </Button>
                    </form>
                </SectionCard>
            )}
            <div className={`${canManage ? "mt-6" : ""} space-y-4`}>
                {agents.map((agent) => (
                    <SectionCard
                        key={agent.public_uuid}
                        title={agent.name}
                        description={
                            agent.description ||
                            agent.purpose ||
                            "No description"
                        }
                        actions={
                            <Badge
                                tone={
                                    agent.status === "active"
                                        ? "success"
                                        : agent.status === "paused"
                                          ? "warning"
                                          : "neutral"
                                }
                            >
                                {agent.status}
                            </Badge>
                        }
                    >
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="text-sm text-slate-500">
                                <p>
                                    {agent.provider || "No provider"} ·{" "}
                                    {agent.model || "provider default"} ·{" "}
                                    {agent.deployment_mode}
                                </p>
                                <p className="mt-1">
                                    {agent.require_citations
                                        ? "Grounding required"
                                        : "Grounding optional"}{" "}
                                    · Version{" "}
                                    {agent.deployed_version || "not deployed"}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                {canManage && (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() => edit(agent)}
                                    >
                                        Edit
                                    </Button>
                                )}
                                {canDeploy && agent.status !== "active" && (
                                    <Button
                                        size="sm"
                                        icon={Play}
                                        onClick={() =>
                                            router.post(
                                                route(
                                                    "tenant.admin.ai.agents.deploy",
                                                    agent.public_uuid,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Deploy
                                    </Button>
                                )}
                                {canDeploy && agent.status === "active" && (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        icon={Pause}
                                        onClick={() =>
                                            router.post(
                                                route(
                                                    "tenant.admin.ai.agents.pause",
                                                    agent.public_uuid,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Pause
                                    </Button>
                                )}
                            </div>
                        </div>
                        {canManage && (
                            <>
                                <ToolPicker agent={agent} tools={tools} />
                                <ChannelPicker
                                    agent={agent}
                                    channels={channels}
                                />
                            </>
                        )}
                        <VersionHistory agent={agent} canManage={canManage} />
                    </SectionCard>
                ))}
            </div>
        </AIShell>
    );
}
