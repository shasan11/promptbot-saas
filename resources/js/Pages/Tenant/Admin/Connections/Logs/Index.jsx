import Pagination from '@/Components/Superadmin/Pagination';
import Badge from '@/Components/UI/Badge';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link } from '@inertiajs/react';

export default function Index({ logs }) {
    return (
        <ConnectionsShell title="Connection logs" description="Structured, redacted operational events for syncs, credentials, webhooks, and provider health.">
            <Head title="Connection logs" />
            <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <tbody className="divide-y divide-slate-100">
                            {logs.data.map((log) => (
                                <tr key={log.id}>
                                    <td className="px-4 py-3"><p className="font-semibold text-slate-900">{log.event}</p><p className="text-xs text-slate-500">{log.message}</p></td>
                                    <td className="px-4 py-3">{log.connection ? <Link className="text-brand-700" href={route('tenant.admin.connections.show', log.connection.id)}>{log.connection.name}</Link> : 'System'}</td>
                                    <td className="px-4 py-3"><Badge tone={log.level === 'warning' ? 'warning' : log.level === 'error' ? 'danger' : 'neutral'}>{log.level}</Badge></td>
                                    <td className="px-4 py-3 text-slate-500">{new Date(log.created_at).toLocaleString()}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
            <Pagination links={logs.links} />
        </ConnectionsShell>
    );
}
