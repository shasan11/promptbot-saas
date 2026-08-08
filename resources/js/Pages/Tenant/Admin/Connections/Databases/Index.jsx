import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/Superadmin/Pagination';
import { HealthBadge, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link } from '@inertiajs/react';
import { Database } from 'lucide-react';

export default function Index({ connections }) {
    return (
        <ConnectionsShell title="Databases" description="Read-only database connections, schema discovery, table sources, and controlled query access.">
            <Head title="Databases" />
            {connections.data.length ? (
                <>
                    <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <tbody className="divide-y divide-slate-100">
                                {connections.data.map((connection) => (
                                    <tr key={connection.id}>
                                        <td className="px-4 py-3"><Link className="font-semibold text-brand-700" href={route('tenant.admin.connections.show', connection.id)}>{connection.name}</Link><div className="text-xs text-slate-500">{connection.provider_account_name}</div></td>
                                        <td className="px-4 py-3"><StatusBadge value={connection.status} /></td>
                                        <td className="px-4 py-3"><HealthBadge value={connection.health_status} /></td>
                                        <td className="px-4 py-3 text-slate-500">{connection.configuration?.read_only ? 'Read-only enforced' : 'Write access warning'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={connections.links} />
                </>
            ) : <EmptyState icon={Database} title="No database connections yet" description="Add a read-only replica before configuring table or view data sources." />}
        </ConnectionsShell>
    );
}
