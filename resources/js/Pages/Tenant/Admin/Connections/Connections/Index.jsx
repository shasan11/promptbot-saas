import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import FilterBar from '@/Components/UI/FilterBar';
import Pagination from '@/Components/Superadmin/Pagination';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import { HealthBadge, StatusBadge, humanize } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link, router } from '@inertiajs/react';
import { Cable, Plus } from 'lucide-react';
import { useState } from 'react';

export default function Index({ connections, filters, statusOptions, healthOptions, typeOptions }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [health, setHealth] = useState(filters.health_status || '');
    const [type, setType] = useState(filters.connection_type || '');
    const apply = (next = {}) => router.get(route('tenant.admin.connections.index'), { search, status, health_status: health, connection_type: type, ...next }, { preserveState: true });

    return (
        <ConnectionsShell title="All connections" description="Manage every tenant-scoped configured instance of an integration." actions={<Button href={route('tenant.admin.connections.create')} variant="brand" icon={Plus}>Add connection</Button>}>
            <Head title="All connections" />
            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
                <FilterBar>
                    <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); apply({ search: '' }); }} placeholder="Search connections" className="w-full max-w-xs" />
                    <Select value={status} onChange={(event) => { setStatus(event.target.value); apply({ status: event.target.value }); }} className="w-48"><option value="">All statuses</option>{statusOptions.map((item) => <option key={item} value={item}>{humanize(item)}</option>)}</Select>
                    <Select value={health} onChange={(event) => { setHealth(event.target.value); apply({ health_status: event.target.value }); }} className="w-48"><option value="">All health</option>{healthOptions.map((item) => <option key={item} value={item}>{humanize(item)}</option>)}</Select>
                    <Select value={type} onChange={(event) => { setType(event.target.value); apply({ connection_type: event.target.value }); }} className="w-48"><option value="">All types</option>{typeOptions.map((item) => <option key={item} value={item}>{humanize(item)}</option>)}</Select>
                    <Button variant="secondary" size="sm" onClick={() => apply()}>Apply</Button>
                </FilterBar>
            </div>

            <div className="mt-4">
                {connections.data.length ? (
                    <>
                        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Connection</th>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Provider</th>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Status</th>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Health</th>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Sources</th>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Last check</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {connections.data.map((connection) => (
                                            <tr key={connection.id} className="hover:bg-slate-50">
                                                <td className="px-4 py-3"><Link href={route('tenant.admin.connections.show', connection.id)} className="font-semibold text-brand-700">{connection.name}</Link><div className="text-xs text-slate-500">{connection.provider_account_name}</div></td>
                                                <td className="px-4 py-3 text-slate-600">{connection.integration?.name}</td>
                                                <td className="px-4 py-3"><StatusBadge value={connection.status} /></td>
                                                <td className="px-4 py-3"><HealthBadge value={connection.health_status} /></td>
                                                <td className="px-4 py-3 text-slate-600">{connection.data_sources_count} data · {connection.resources_count} resources</td>
                                                <td className="px-4 py-3 text-slate-500">{connection.last_checked_at ? new Date(connection.last_checked_at).toLocaleString() : 'Never'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <Pagination links={connections.links} />
                    </>
                ) : <EmptyState icon={Cable} title="No apps are connected yet" description="Connect an app to start discovering resources and creating data sources." action={<Button href={route('tenant.admin.connections.create')} variant="brand" icon={Plus}>Connect an app</Button>} />}
            </div>
        </ConnectionsShell>
    );
}
