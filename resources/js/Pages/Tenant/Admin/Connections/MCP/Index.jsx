import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/Superadmin/Pagination';
import { HealthBadge, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link } from '@inertiajs/react';
import { ServerCog } from 'lucide-react';

export default function Index({ connections }) {
    return (
        <ConnectionsShell title="MCP servers" description="Register MCP servers, discover tools and resources, and expose approved capabilities to AI agents.">
            <Head title="MCP servers" />
            {connections.data.length ? (
                <>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {connections.data.map((connection) => (
                            <Link key={connection.id} href={route('tenant.admin.connections.show', connection.id)} className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft hover:bg-slate-50">
                                <p className="font-semibold text-slate-900">{connection.name}</p>
                                <div className="mt-4 flex gap-2"><StatusBadge value={connection.status} /><HealthBadge value={connection.health_status} /></div>
                            </Link>
                        ))}
                    </div>
                    <Pagination links={connections.links} />
                </>
            ) : <EmptyState icon={ServerCog} title="No MCP servers connected" description="MCP tool discovery and risk policies will appear once a server is connected." />}
        </ConnectionsShell>
    );
}
