import Pagination from '@/Components/Superadmin/Pagination';
import Badge from '@/Components/UI/Badge';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link } from '@inertiajs/react';

export default function Index({ syncRuns }) {
    return (
        <ConnectionsShell title="Sync jobs" description="Full, incremental, webhook-driven, and manual sync executions with operational metrics.">
            <Head title="Sync jobs" />
            <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <tbody className="divide-y divide-slate-100">
                            {syncRuns.data.map((run) => (
                                <tr key={run.id}>
                                    <td className="px-4 py-3"><Link className="font-semibold text-brand-700" href={route('tenant.admin.connections.show', run.connection?.id)}>{run.connection?.name || 'Deleted connection'}</Link><div className="text-xs text-slate-500">{run.data_source?.name || 'Connection sync'}</div></td>
                                    <td className="px-4 py-3"><Badge tone={run.status === 'completed' ? 'brand' : run.status === 'failed' ? 'danger' : 'warning'}>{run.status}</Badge></td>
                                    <td className="px-4 py-3 text-slate-600">{run.items_discovered} discovered · {run.items_failed} failed</td>
                                    <td className="px-4 py-3 text-slate-500">{run.started_at ? new Date(run.started_at).toLocaleString() : 'Queued'}</td>
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
