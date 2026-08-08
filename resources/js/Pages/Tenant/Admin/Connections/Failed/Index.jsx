import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/Superadmin/Pagination';
import { HealthBadge, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

export default function Index({ connections }) {
    return (
        <ConnectionsShell title="Failed connections" description="Connections requiring reauthentication, rate-limit backoff, provider review, or configuration repair.">
            <Head title="Failed connections" />
            {connections.data.length ? (
                <>
                    <div className="space-y-3">
                        {connections.data.map((connection) => (
                            <Link key={connection.id} href={route('tenant.admin.connections.show', connection.id)} className="block rounded-lg border border-amber-200 bg-amber-50/50 p-5 shadow-soft">
                                <div className="flex items-start justify-between gap-3">
                                    <div><p className="font-semibold text-slate-900">{connection.name}</p><p className="mt-1 text-sm text-amber-800">{connection.last_error_message}</p></div>
                                    <div className="flex gap-2"><StatusBadge value={connection.status} /><HealthBadge value={connection.health_status} /></div>
                                </div>
                            </Link>
                        ))}
                    </div>
                    <Pagination links={connections.links} />
                </>
            ) : <EmptyState icon={AlertTriangle} title="All connections are healthy" description="No connection currently needs operational attention." />}
        </ConnectionsShell>
    );
}
