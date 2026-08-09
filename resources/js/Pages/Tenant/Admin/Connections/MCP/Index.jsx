import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/Superadmin/Pagination';
import { HealthBadge, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Save, ServerCog, ShieldAlert, Wrench } from 'lucide-react';
import { useState } from 'react';

const riskClass = {
    low: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    medium: 'bg-amber-50 text-amber-700 ring-amber-200',
    high: 'bg-orange-50 text-orange-700 ring-orange-200',
    critical: 'bg-rose-50 text-rose-700 ring-rose-200',
};

function ToolPolicyForm({ connection, tool }) {
    const { data, setData, put, processing, errors } = useForm({
        enabled_for_ai: Boolean(tool.enabled_for_ai),
        enabled_for_workflows: Boolean(tool.enabled_for_workflows),
        requires_approval: Boolean(tool.requires_approval),
        status: tool.status || 'active',
    });

    const submit = (event) => {
        event.preventDefault();
        put(route('tenant.admin.connections.mcp-tools.update', [connection.id, tool.id]), {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-semibold text-slate-900">{tool.name}</p>
                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ${riskClass[tool.risk_level] || riskClass.medium}`}>
                            {tool.risk_level}
                        </span>
                        {(tool.enabled_for_ai || tool.enabled_for_workflows) && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                                <CheckCircle2 className="h-3 w-3" /> Enabled
                            </span>
                        )}
                    </div>
                    <p className="mt-1 text-sm text-slate-500">{tool.key}</p>
                    {tool.description ? <p className="mt-2 text-sm text-slate-600">{tool.description}</p> : null}
                </div>
                <button type="submit" disabled={processing} className="inline-flex h-9 items-center gap-2 rounded-md bg-slate-900 px-3 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60">
                    <Save className="h-4 w-4" /> Save
                </button>
            </div>

            <div className="mt-4 grid gap-3 text-sm sm:grid-cols-4">
                <label className="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2">
                    <input type="checkbox" checked={data.enabled_for_ai} onChange={(event) => setData('enabled_for_ai', event.target.checked)} />
                    <span>AI</span>
                </label>
                <label className="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2">
                    <input type="checkbox" checked={data.enabled_for_workflows} onChange={(event) => setData('enabled_for_workflows', event.target.checked)} />
                    <span>Workflows</span>
                </label>
                <label className="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2">
                    <input type="checkbox" checked={data.requires_approval} onChange={(event) => setData('requires_approval', event.target.checked)} />
                    <span>Approval</span>
                </label>
                <label className="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2">
                    <input type="checkbox" checked={data.status === 'active'} onChange={(event) => setData('status', event.target.checked ? 'active' : 'disabled')} />
                    <span>Active</span>
                </label>
            </div>
            {errors.enabled_for_ai ? (
                <p className="mt-3 flex items-center gap-2 text-sm text-rose-600"><ShieldAlert className="h-4 w-4" /> {errors.enabled_for_ai}</p>
            ) : null}
        </form>
    );
}

function DiscoverToolForm({ connection, riskLevels }) {
    const [schemaText, setSchemaText] = useState('{"type":"object","properties":{}}');
    const [schemaError, setSchemaError] = useState(null);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        key: '',
        name: '',
        description: '',
        risk_level: 'low',
        input_schema: {},
        output_schema: {},
        capabilities: [],
        discovery_source: 'admin_review',
    });

    const submit = (event) => {
        event.preventDefault();

        try {
            const parsedSchema = schemaText.trim() ? JSON.parse(schemaText) : {};
            setSchemaError(null);
            transform((values) => ({ ...values, input_schema: parsedSchema }));
            post(route('tenant.admin.connections.mcp-tools.store', connection.id), {
                preserveScroll: true,
                onSuccess: () => {
                    reset('key', 'name', 'description');
                    setSchemaText('{"type":"object","properties":{}}');
                },
            });
        } catch {
            setSchemaError('Input schema must be valid JSON.');
        }
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
            <div className="grid gap-3 lg:grid-cols-4">
                <input value={data.key} onChange={(event) => setData('key', event.target.value)} placeholder="tool.key" className="rounded-md border-slate-300 text-sm" />
                <input value={data.name} onChange={(event) => setData('name', event.target.value)} placeholder="Tool name" className="rounded-md border-slate-300 text-sm" />
                <select value={data.risk_level} onChange={(event) => setData('risk_level', event.target.value)} className="rounded-md border-slate-300 text-sm">
                    {riskLevels.map((level) => <option key={level} value={level}>{level}</option>)}
                </select>
                <button type="submit" disabled={processing} className="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-white px-3 text-sm font-semibold text-slate-800 ring-1 ring-slate-300 hover:bg-slate-100 disabled:opacity-60">
                    <Wrench className="h-4 w-4" /> Add Tool
                </button>
            </div>
            <textarea value={data.description} onChange={(event) => setData('description', event.target.value)} placeholder="Description" className="mt-3 min-h-16 w-full rounded-md border-slate-300 text-sm" />
            <textarea value={schemaText} onChange={(event) => setSchemaText(event.target.value)} className="mt-3 min-h-20 w-full rounded-md border-slate-300 font-mono text-xs" />
            {(schemaError || errors.key || errors.name) ? (
                <p className="mt-2 text-sm text-rose-600">{schemaError || errors.key || errors.name}</p>
            ) : null}
        </form>
    );
}

export default function Index({ connections, riskLevels = ['low', 'medium', 'high', 'critical'] }) {
    return (
        <ConnectionsShell title="MCP servers" description="Register MCP servers, discover tools and resources, and expose approved capabilities to AI agents.">
            <Head title="MCP servers" />
            {connections.data.length ? (
                <>
                    <div className="space-y-5">
                        {connections.data.map((connection) => (
                            <section key={connection.id} className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <Link href={route('tenant.admin.connections.show', connection.id)} className="text-lg font-semibold text-slate-900 hover:text-blue-700">
                                            {connection.name}
                                        </Link>
                                        <p className="mt-1 text-sm text-slate-500">{connection.integration?.name} · {connection.configuration?.server_url || connection.configuration?.base_url || 'No server URL'}</p>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <StatusBadge value={connection.status} />
                                            <HealthBadge value={connection.health_status} />
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-3 gap-2 text-center text-sm">
                                        <div className="rounded-md border border-slate-200 px-3 py-2">
                                            <p className="font-semibold text-slate-900">{connection.mcp_tools_count}</p>
                                            <p className="text-xs text-slate-500">Tools</p>
                                        </div>
                                        <div className="rounded-md border border-slate-200 px-3 py-2">
                                            <p className="font-semibold text-slate-900">{connection.enabled_mcp_tools_count}</p>
                                            <p className="text-xs text-slate-500">Enabled</p>
                                        </div>
                                        <div className="rounded-md border border-slate-200 px-3 py-2">
                                            <p className="font-semibold text-slate-900">{connection.resources_count}</p>
                                            <p className="text-xs text-slate-500">Resources</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-5 grid gap-4 xl:grid-cols-[1fr_22rem]">
                                    <div className="space-y-3">
                                        {connection.mcp_tools?.length ? (
                                            connection.mcp_tools.map((tool) => <ToolPolicyForm key={tool.id} connection={connection} tool={tool} />)
                                        ) : (
                                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No tools discovered.</div>
                                        )}
                                    </div>
                                    <DiscoverToolForm connection={connection} riskLevels={riskLevels} />
                                </div>
                            </section>
                        ))}
                    </div>
                    <Pagination links={connections.links} />
                </>
            ) : <EmptyState icon={ServerCog} title="No MCP servers connected" description="MCP tool discovery and risk policies will appear once a server is connected." />}
        </ConnectionsShell>
    );
}
