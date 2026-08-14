import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { HealthBadge, StatusBadge, humanize } from '@/Components/Tenant/Connections/ConnectionBadges';
import { Head, Link } from '@inertiajs/react';
import { Activity, AppWindow, Cable, Plus, RotateCcw } from 'lucide-react';

function Metric({ label, value }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</div>
            <div className="mt-2 text-2xl font-bold tracking-tight text-slate-900">{value ?? '—'}</div>
        </div>
    );
}

export default function Overview({ metrics, healthSummary, recentActivity, issues, catalogHighlights }) {
    return (
        <ConnectionsShell
            title="Connections"
            description="Connect PromptBot with the tools, data, and services your business already uses."
            actions={(
                <>
                    <Button href={route('tenant.admin.connections.create')} variant="brand" icon={Plus}>Add connection</Button>
                    <Button href={route('tenant.admin.connections.apps.index')} variant="secondary" icon={AppWindow}>Browse apps</Button>
                </>
            )}
        >
            <Head title="Connections" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <Metric label="Total connections" value={metrics.totalConnections} />
                <Metric label="Active" value={metrics.activeConnections} />
                <Metric label="Needs attention" value={metrics.needsAttention} />
                <Metric label="Data sources" value={metrics.dataSources} />
                <Metric label="Scheduled syncs" value={metrics.scheduledSyncs} />
                <Metric label="Failed syncs" value={metrics.failedSyncs} />
                <Metric label="API requests today" value={metrics.apiRequestsToday} />
                <Metric label="Webhook events today" value={metrics.webhookEventsToday} />
                <Metric label="Connected apps" value={metrics.connectedApplications} />
                <Metric label="Last successful sync" value={metrics.lastSuccessfulSync ? new Date(metrics.lastSuccessfulSync).toLocaleString() : 'None'} />
            </div>

            <div className="mt-6 space-y-6">
                <SectionCard title="Connection health">
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {healthSummary.map((item) => (
                            <Link key={item.status} href={route('tenant.admin.connections.index', { health_status: item.status })} className="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                                <HealthBadge value={item.status} />
                                <span className="font-semibold text-slate-900">{item.count}</span>
                            </Link>
                        ))}
                    </div>
                </SectionCard>

                <SectionCard title="Recent activity">
                    {recentActivity.length ? (
                        <ul className="space-y-3">
                            {recentActivity.map((entry) => (
                                <li key={entry.id} className="flex gap-3 text-sm">
                                    <Activity className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                    <div className="min-w-0">
                                        <p className="truncate font-medium text-slate-800">{entry.message || humanize(entry.event)}</p>
                                        <p className="text-xs text-slate-500">{entry.connection?.name || 'System'} · {new Date(entry.created_at).toLocaleString()}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : <EmptyState icon={Activity} title="No connection activity yet" description="Tests, syncs, OAuth refreshes, and webhook events will appear here." />}
                </SectionCard>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <SectionCard title="Needs attention">
                    {issues.length ? (
                        <div className="space-y-3">
                            {issues.map((connection) => (
                                <Link key={connection.id} href={route('tenant.admin.connections.show', connection.id)} className="block rounded-md border border-amber-200 bg-amber-50/40 p-4 hover:shadow-soft">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-900">{connection.name}</p>
                                            <p className="mt-1 text-xs text-slate-500">{connection.last_error_message || connection.integration?.name}</p>
                                        </div>
                                        <StatusBadge value={connection.status} />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    ) : <EmptyState icon={Cable} title="All connections are healthy" description="No active integration needs attention right now." />}
                </SectionCard>

                <SectionCard title="Catalog highlights">
                    <div className="grid gap-3 sm:grid-cols-2">
                        {catalogHighlights.map((integration) => (
                            <Link key={integration.id} href={route('tenant.admin.connections.apps.show', integration.key)} className="rounded-md border border-slate-200 p-4 hover:bg-slate-50">
                                <p className="font-semibold text-slate-900">{integration.name}</p>
                                <p className="mt-1 text-xs text-slate-500">{integration.provider} · {integration.category}</p>
                                <p className="mt-3 text-xs font-semibold text-brand-700">{integration.connections_count} connected</p>
                            </Link>
                        ))}
                    </div>
                </SectionCard>
            </div>
        </ConnectionsShell>
    );
}
