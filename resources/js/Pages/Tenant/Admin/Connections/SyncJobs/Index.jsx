import Button from '@/Components/UI/Button';
import Pagination from '@/Components/Superadmin/Pagination';
import Badge from '@/Components/UI/Badge';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link, router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';

export default function Index({ syncRuns }) {
    const retryable = ['failed', 'completed_with_errors', 'rate_limited', 'waiting_for_auth'];
    const tone = (status) => {
        if (status === 'completed') return 'brand';
        if (status === 'failed') return 'danger';
        return 'warning';
    };

    return (
        <ConnectionsShell title="Sync jobs" description="Full, incremental, webhook-driven, and manual sync executions with operational metrics.">
            <Head title="Sync jobs" />
            <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3">Sync</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Items</th>
                                <th className="px-4 py-3">Started</th>
                                <th className="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {syncRuns.data.map((run) => (
                                <tr key={run.id}>
                                    <td className="px-4 py-3">
                                        <Link className="font-semibold text-brand-700" href={run.connection ? route('tenant.admin.connections.show', run.connection.id) : route('tenant.admin.connections.sync-jobs.index')}>
                                            {run.connection?.name || 'Deleted connection'}
                                        </Link>
                                        <div className="text-xs text-slate-500">{run.data_source?.name || 'Connection sync'}</div>
                                        {run.error_summary && <div className="mt-1 text-xs text-rose-700">{run.error_summary}</div>}
                                    </td>
                                    <td className="px-4 py-3"><Badge tone={tone(run.status)}>{run.status}</Badge></td>
                                    <td className="px-4 py-3 text-slate-600">{run.items_discovered} discovered - {run.items_failed} failed</td>
                                    <td className="px-4 py-3 text-slate-500">{run.started_at ? new Date(run.started_at).toLocaleString() : 'Queued'}</td>
                                    <td className="px-4 py-3">
                                        {retryable.includes(run.status) && (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                icon={RotateCcw}
                                                onClick={() => router.post(route('tenant.admin.connections.sync-jobs.retry', run.id), {}, { preserveScroll: true })}
                                            >
                                                Retry
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
            <Pagination links={syncRuns.links} />
        </ConnectionsShell>
    );
}
