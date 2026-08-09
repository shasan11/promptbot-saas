import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/Superadmin/Pagination';
import { HealthBadge, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, FileText, Pencil, PlugZap, RotateCcw, SearchCheck, Slash } from 'lucide-react';

const formatDate = (value) => (value ? new Date(value).toLocaleString() : 'Never');

function severity(connection) {
    if (connection.health_status === 'authentication_expired' || connection.status === 'authentication_required') return 'Critical';
    if (connection.health_status === 'error') return 'High';
    if (connection.health_status === 'rate_limited') return 'Medium';
    return 'Warning';
}

function suggestedFix(connection) {
    if (connection.health_status === 'authentication_expired' || connection.status === 'authentication_required') return 'Reconnect credentials';
    if (connection.health_status === 'rate_limited') return 'Retry after backoff';
    if (connection.latest_failed_sync_run) return 'Retry latest failed sync';
    return 'Test connection';
}

export default function Index({ connections }) {
    const post = (name, id) => router.post(route(name, id), {}, { preserveScroll: true });

    return (
        <ConnectionsShell title="Failed connections" description="Connections requiring reauthentication, rate-limit backoff, provider review, or configuration repair.">
            <Head title="Failed connections" />
            {connections.data.length ? (
                <>
                    <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Connection</th>
                                        <th className="px-4 py-3">Severity</th>
                                        <th className="px-4 py-3">Last success</th>
                                        <th className="px-4 py-3">Last failure</th>
                                        <th className="px-4 py-3">Attempts</th>
                                        <th className="px-4 py-3">Suggested fix</th>
                                        <th className="px-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {connections.data.map((connection) => {
                                        const failedRun = connection.latest_failed_sync_run;
                                        const successfulRun = connection.latest_successful_sync_run;

                                        return (
                                            <tr key={connection.id} className="align-top">
                                                <td className="px-4 py-3">
                                                    <Link href={route('tenant.admin.connections.show', connection.id)} className="font-semibold text-brand-700">
                                                        {connection.name}
                                                    </Link>
                                                    <div className="mt-1 text-xs text-slate-500">{connection.integration?.name || connection.integration?.provider}</div>
                                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                                        <StatusBadge value={connection.status} />
                                                        <HealthBadge value={connection.health_status} />
                                                    </div>
                                                    {connection.last_error_message && <p className="mt-2 max-w-xs text-xs text-rose-700">{connection.last_error_message}</p>}
                                                </td>
                                                <td className="px-4 py-3 font-semibold text-slate-700">{severity(connection)}</td>
                                                <td className="px-4 py-3 text-slate-600">{formatDate(successfulRun?.completed_at)}</td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    {formatDate(failedRun?.completed_at || connection.last_error_at)}
                                                    {failedRun?.error_summary && <div className="mt-1 max-w-xs text-xs text-rose-700">{failedRun.error_summary}</div>}
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    <div>{connection.failed_sync_runs_count || 0} failed</div>
                                                    <div className="text-xs text-slate-500">{failedRun ? `${failedRun.retry_count || 0} retries` : 'No sync run'}</div>
                                                </td>
                                                <td className="px-4 py-3 text-slate-700">{suggestedFix(connection)}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex min-w-[330px] flex-wrap gap-2">
                                                        <Button size="sm" variant="secondary" icon={PlugZap} onClick={() => post('tenant.admin.connections.reconnect', connection.id)}>Reconnect</Button>
                                                        <Button size="sm" variant="secondary" icon={SearchCheck} onClick={() => post('tenant.admin.connections.test', connection.id)}>Test</Button>
                                                        <Button
                                                            size="sm"
                                                            variant="secondary"
                                                            icon={RotateCcw}
                                                            disabled={!failedRun}
                                                            onClick={() => failedRun && post('tenant.admin.connections.retry-failed-sync', connection.id)}
                                                        >
                                                            Retry
                                                        </Button>
                                                        <Button size="sm" variant="secondary" icon={FileText} href={route('tenant.admin.connections.logs.index', { connection: connection.id })}>Logs</Button>
                                                        <Button size="sm" variant="secondary" icon={Pencil} href={route('tenant.admin.connections.edit', connection.id)}>Edit</Button>
                                                        <Button size="sm" variant="danger" icon={Slash} onClick={() => post('tenant.admin.connections.disable', connection.id)}>Disable</Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <Pagination links={connections.links} />
                </>
            ) : <EmptyState icon={AlertTriangle} title="All connections are healthy" description="No connection currently needs operational attention." />}
        </ConnectionsShell>
    );
}
