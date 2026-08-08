import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/Superadmin/Pagination';
import { StatusBadge, humanize } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, RotateCcw } from 'lucide-react';

export default function Index({ dataSources }) {
    return (
        <ConnectionsShell title="Data sources" description="External resources selected for knowledge, agents, workflows, search, analytics, or synchronization.">
            <Head title="Data sources" />
            {dataSources.data.length ? (
                <>
                    <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Data source</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Connection</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Type</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Sync</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Last sync</th>
                                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Records</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {dataSources.data.map((source) => (
                                        <tr key={source.id}>
                                            <td className="px-4 py-3"><span className="font-semibold text-slate-900">{source.name}</span><div className="mt-1"><StatusBadge value={source.status} /></div></td>
                                            <td className="px-4 py-3"><Link className="text-brand-700" href={route('tenant.admin.connections.show', source.connection.id)}>{source.connection.name}</Link><div className="text-xs text-slate-500">{source.connection.integration?.name}</div></td>
                                            <td className="px-4 py-3 text-slate-600">{humanize(source.resource_type)}</td>
                                            <td className="px-4 py-3 text-slate-600">{humanize(source.sync_mode)} · {source.sync_schedule || 'manual'}</td>
                                            <td className="px-4 py-3 text-slate-500">{source.last_synced_at ? new Date(source.last_synced_at).toLocaleString() : 'Never'}</td>
                                            <td className="px-4 py-3 text-slate-600">{source.records_synced}</td>
                                            <td className="px-4 py-3 text-right"><Button size="sm" variant="secondary" icon={RotateCcw} onClick={() => router.post(route('tenant.admin.connections.data-sources.sync', source.id), {}, { preserveScroll: true })}>Sync</Button></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <Pagination links={dataSources.links} />
                </>
            ) : <EmptyState icon={ClipboardList} title="No data sources are configured" description="Discover resources from a connection, then select the resources PromptBot may use." />}
        </ConnectionsShell>
    );
}
