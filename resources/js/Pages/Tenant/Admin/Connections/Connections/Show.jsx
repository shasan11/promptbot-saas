import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import { CapabilityList, HealthBadge, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Activity, KeyRound, Play, PlugZap, RotateCcw, SearchCheck, Trash2 } from 'lucide-react';

export default function Show({ connection, deleteImpact }) {
    const deleteForm = useForm({
        confirmation: '',
        reason: '',
    });
    const impactItems = [
        ['AI agents', deleteImpact.ai_agents],
        ['Workflows', deleteImpact.workflows],
        ['Data sources', deleteImpact.data_sources],
        ['Scheduled syncs', deleteImpact.scheduled_syncs],
        ['Running/queued syncs', deleteImpact.running_or_queued_syncs],
        ['Webhook endpoints', deleteImpact.webhook_endpoints],
        ['Active credentials', deleteImpact.active_credentials],
        ['Actions', deleteImpact.actions],
        ['Triggers', deleteImpact.triggers],
    ];
    const deleteReady = deleteForm.data.confirmation === connection.name;

    const destroy = (event) => {
        event.preventDefault();
        deleteForm.delete(route('tenant.admin.connections.destroy', connection.id), {
            preserveScroll: true,
        });
    };

    return (
        <ConnectionsShell
            title={connection.name}
            description={`${connection.integration?.name || 'Custom'} · ${connection.provider_account_name || 'No provider account recorded'}`}
            actions={(
                <>
                    <Button variant="secondary" icon={SearchCheck} onClick={() => router.post(route('tenant.admin.connections.test', connection.id), {}, { preserveScroll: true })}>Test</Button>
                    <Button variant="secondary" icon={PlugZap} onClick={() => router.post(route('tenant.admin.connections.discover', connection.id), {}, { preserveScroll: true })}>Discover</Button>
                    <Button variant="brand" icon={RotateCcw} onClick={() => router.post(route('tenant.admin.connections.sync', connection.id), {}, { preserveScroll: true })}>Sync now</Button>
                </>
            )}
        >
            <Head title={connection.name} />
            <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                <SectionCard title="Overview">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div><p className="text-xs font-semibold uppercase text-slate-500">Status</p><div className="mt-2"><StatusBadge value={connection.status} /></div></div>
                        <div><p className="text-xs font-semibold uppercase text-slate-500">Health</p><div className="mt-2"><HealthBadge value={connection.health_status} /></div></div>
                        <div><p className="text-xs font-semibold uppercase text-slate-500">Auth</p><p className="mt-2 text-sm text-slate-700">{connection.auth_type}</p></div>
                        <div><p className="text-xs font-semibold uppercase text-slate-500">Last check</p><p className="mt-2 text-sm text-slate-700">{connection.last_checked_at ? new Date(connection.last_checked_at).toLocaleString() : 'Never'}</p></div>
                        <div><p className="text-xs font-semibold uppercase text-slate-500">Data sources</p><p className="mt-2 text-sm text-slate-700">{connection.data_sources_count}</p></div>
                        <div><p className="text-xs font-semibold uppercase text-slate-500">Resources</p><p className="mt-2 text-sm text-slate-700">{connection.resources_count}</p></div>
                    </div>
                    {connection.last_error_message && <div className="mt-5 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">{connection.last_error_message}</div>}
                </SectionCard>

                <SectionCard title="Capabilities">
                    <CapabilityList capabilities={connection.integration?.capabilities || []} limit={20} />
                </SectionCard>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <SectionCard title="Resources">
                    <ul className="divide-y divide-slate-100 text-sm">
                        {connection.resources.map((resource) => <li key={resource.id} className="py-3"><span className="font-medium text-slate-800">{resource.name}</span><span className="ml-2 text-xs text-slate-500">{resource.resource_type} · {resource.path}</span></li>)}
                    </ul>
                </SectionCard>
                <SectionCard title="Data sources">
                    <ul className="divide-y divide-slate-100 text-sm">
                        {connection.data_sources.map((source) => <li key={source.id} className="flex items-center justify-between gap-3 py-3"><Link className="font-medium text-brand-700" href={route('tenant.admin.connections.data-sources.index')}>{source.name}</Link><span className="text-xs text-slate-500">{source.sync_mode}</span></li>)}
                    </ul>
                </SectionCard>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <SectionCard title="Credentials" actions={<KeyRound className="h-4 w-4 text-slate-400" />}>
                    <ul className="divide-y divide-slate-100 text-sm">
                        {connection.credentials.map((credential) => <li key={credential.id} className="flex justify-between py-3"><span>{credential.type}</span><span className="font-mono text-xs text-slate-500">{credential.masked_secret || 'No secret shown'}</span></li>)}
                    </ul>
                </SectionCard>
                <SectionCard title="Recent logs" actions={<Activity className="h-4 w-4 text-slate-400" />}>
                    <ul className="divide-y divide-slate-100 text-sm">
                        {connection.logs.map((log) => <li key={log.id} className="py-3"><p className="font-medium text-slate-800">{log.message || log.event}</p><p className="text-xs text-slate-500">{new Date(log.created_at).toLocaleString()}</p></li>)}
                    </ul>
                </SectionCard>
            </div>

            <div className="mt-6">
                <SectionCard title="Delete impact">
                    <div className="grid gap-6 xl:grid-cols-[1fr_420px]">
                        <div>
                            <p className="text-sm text-slate-600">Deleting this connection disables dependent automation, cancels unfinished syncs, revokes and scrubs stored credentials, disables webhooks, and keeps logs for audit review.</p>
                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                {impactItems.map(([label, value]) => (
                                    <div key={label} className="rounded-md border border-slate-200 bg-slate-50 p-3">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
                                        <p className="mt-1 text-2xl font-bold text-slate-900">{value}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <form className="rounded-md border border-rose-200 bg-rose-50 p-4" onSubmit={destroy}>
                            <p className="text-sm font-semibold text-rose-900">Remove connection</p>
                            <p className="mt-1 text-xs text-rose-800">Type the connection name to confirm: <span className="font-mono">{connection.name}</span></p>
                            <input
                                className="mt-3 w-full rounded-md border-rose-200 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"
                                value={deleteForm.data.confirmation}
                                onChange={(event) => deleteForm.setData('confirmation', event.target.value)}
                            />
                            <textarea
                                className="mt-3 w-full rounded-md border-rose-200 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"
                                rows="3"
                                placeholder="Reason"
                                value={deleteForm.data.reason}
                                onChange={(event) => deleteForm.setData('reason', event.target.value)}
                            />
                            <Button className="mt-3" variant="danger" icon={Trash2} type="submit" loading={deleteForm.processing} disabled={!deleteReady}>
                                Delete safely
                            </Button>
                            {deleteForm.errors.confirmation && <p className="mt-2 text-xs text-rose-700">{deleteForm.errors.confirmation}</p>}
                        </form>
                    </div>
                </SectionCard>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <SectionCard title="Health checks">
                    <ul className="divide-y divide-slate-100 text-sm">
                        {connection.health_checks.map((check) => (
                            <li key={check.id} className="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <p className="font-medium text-slate-800">{check.message}</p>
                                    <p className="text-xs text-slate-500">{new Date(check.checked_at).toLocaleString()} · {check.duration_ms} ms</p>
                                </div>
                                <HealthBadge value={check.health_status} />
                            </li>
                        ))}
                    </ul>
                </SectionCard>
                <SectionCard title="Provider quota">
                    <ul className="divide-y divide-slate-100 text-sm">
                        {connection.rate_limits.map((limit) => (
                            <li key={limit.id} className="py-3">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="font-medium text-slate-800">{limit.provider} · {limit.bucket}</span>
                                    <span className="text-slate-600">{limit.remaining ?? '—'} / {limit.limit ?? '—'}</span>
                                </div>
                                <p className="mt-1 text-xs text-slate-500">Reset {limit.resets_at ? new Date(limit.resets_at).toLocaleString() : 'unknown'}{limit.backoff_until ? ` · Backoff until ${new Date(limit.backoff_until).toLocaleString()}` : ''}</p>
                            </li>
                        ))}
                    </ul>
                </SectionCard>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <SectionCard title="AI and workflow actions">
                    <ul className="divide-y divide-slate-100 text-sm">
                        {connection.actions.map((action) => (
                            <li key={action.id} className="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <p className="font-medium text-slate-800">{action.name}</p>
                                    <p className="text-xs text-slate-500">{action.key} · {action.risk_level} risk{action.requires_approval ? ' · approval required' : ''}</p>
                                </div>
                                <Button
                                    size="sm"
                                    variant={action.requires_approval ? 'secondary' : 'brand'}
                                    icon={Play}
                                    onClick={() => router.post(route('tenant.admin.connections.actions.execute', [connection.id, action.id]), { input: {}, idempotency_key: `${action.key}-${Date.now()}` }, { preserveScroll: true })}
                                >
                                    {action.requires_approval ? 'Request' : 'Run'}
                                </Button>
                            </li>
                        ))}
                    </ul>
                </SectionCard>
                <SectionCard title="Triggers and API operations">
                    <div className="space-y-4 text-sm">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Triggers</p>
                            <ul className="mt-2 divide-y divide-slate-100">
                                {connection.triggers.map((trigger) => <li key={trigger.id} className="py-2"><span className="font-medium text-slate-800">{trigger.name}</span><span className="ml-2 text-xs text-slate-500">{trigger.key}</span></li>)}
                            </ul>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">API operations</p>
                            <ul className="mt-2 divide-y divide-slate-100">
                                {connection.api_operations.map((operation) => <li key={operation.id} className="py-2"><span className="font-medium text-slate-800">{operation.name}</span><span className="ml-2 text-xs text-slate-500">{operation.method} {operation.path}</span></li>)}
                            </ul>
                        </div>
                    </div>
                </SectionCard>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <SectionCard title="Action executions">
                    <ul className="divide-y divide-slate-100 text-sm">
                        {connection.action_executions.map((execution) => (
                            <li key={execution.id} className="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <p className="font-medium text-slate-800">{execution.action?.name || 'Unknown action'}</p>
                                    <p className="text-xs text-slate-500">{execution.status} · {execution.duration_ms || 0} ms</p>
                                </div>
                                <StatusBadge value={execution.status} />
                            </li>
                        ))}
                    </ul>
                </SectionCard>
                <SectionCard title="Usage and access">
                    <div className="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">AI agents</p>
                            <p className="mt-2 text-2xl font-bold text-slate-900">{connection.agent_access.length}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Workflows</p>
                            <p className="mt-2 text-2xl font-bold text-slate-900">{connection.workflow_access.length}</p>
                        </div>
                    </div>
                    <ul className="mt-4 divide-y divide-slate-100 text-sm">
                        {connection.usage_records.map((record) => <li key={record.id} className="flex justify-between py-2"><span>{record.usage_type}</span><span className="font-semibold text-slate-700">{record.quantity} {record.unit}</span></li>)}
                    </ul>
                </SectionCard>
            </div>
        </ConnectionsShell>
    );
}
