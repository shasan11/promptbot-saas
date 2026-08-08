import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/Superadmin/Pagination';
import { HealthBadge, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link } from '@inertiajs/react';
import { Braces } from 'lucide-react';

export default function Index({ connections }) {
    return (
        <ConnectionsShell title="API connections" description="Custom REST, GraphQL, and internal API connections with scoped operations and safe action exposure.">
            <Head title="API connections" />
            {connections.data.length ? (
                <>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {connections.data.map((connection) => (
                            <Link key={connection.id} href={route('tenant.admin.connections.show', connection.id)} className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft hover:bg-slate-50">
                                <p className="font-semibold text-slate-900">{connection.name}</p>
                                <p className="mt-1 text-xs text-slate-500">{connection.provider_account_name}</p>
                                <div className="mt-4 flex flex-wrap gap-2"><StatusBadge value={connection.status} /><HealthBadge value={connection.health_status} /></div>
                            </Link>
                        ))}
                    </div>
                    <Pagination links={connections.links} />
                </>
            ) : <EmptyState icon={Braces} title="No API connections yet" description="Create a custom API connection to expose approved endpoints to workflows and AI actions." />}
        </ConnectionsShell>
    );
}
