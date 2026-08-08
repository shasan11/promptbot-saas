import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import SourceTypeBadge from '@/Components/Knowledge/SourceTypeBadge';
import Pagination from '@/Components/Superadmin/Pagination';
import EmptyState from '@/Components/UI/EmptyState';
import { FilterBar } from '@/Components/UI/FilterBar';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import { Link, router } from '@inertiajs/react';
import { Database } from 'lucide-react';
import { useState } from 'react';

export default function SourcesIndex({ sources, filters, bases, types, statuses }) {
    const [search, setSearch] = useState(filters.search || '');

    const applyFilter = (changes) => router.get(route('tenant.admin.knowledge.sources.index'), { ...filters, ...changes }, {
        preserveState: true, preserveScroll: true, replace: true,
    });

    return (
        <KnowledgeShell
            title="All sources"
            description="Everywhere your knowledge comes from, and whether each one is healthy."
        >
            <FilterBar className="mb-4">
                <form onSubmit={(e) => { e.preventDefault(); applyFilter({ search }); }} className="min-w-[14rem] flex-1">
                    <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); applyFilter({ search: '' }); }} placeholder="Search sources" />
                </form>
                <div className="w-44">
                    <label htmlFor="src-base" className="sr-only">Filter by knowledge base</label>
                    <Select id="src-base" value={filters.knowledge_base || ''} onChange={(e) => applyFilter({ knowledge_base: e.target.value })}>
                        <option value="">All knowledge bases</option>
                        {bases.map((b) => <option key={b.uuid} value={b.uuid}>{b.name}</option>)}
                    </Select>
                </div>
                <div className="w-40">
                    <label htmlFor="src-type" className="sr-only">Filter by type</label>
                    <Select id="src-type" value={filters.type || ''} onChange={(e) => applyFilter({ type: e.target.value })}>
                        <option value="">All types</option>
                        {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                    </Select>
                </div>
                <div className="w-40">
                    <label htmlFor="src-status" className="sr-only">Filter by status</label>
                    <Select id="src-status" value={filters.status || ''} onChange={(e) => applyFilter({ status: e.target.value })}>
                        <option value="">All statuses</option>
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </Select>
                </div>
            </FilterBar>

            {sources.data.length === 0 ? (
                <EmptyState
                    icon={Database}
                    title="No sources found"
                    description="Sources appear here once you upload documents, index a website, or create FAQs."
                />
            ) : (
                <>
                    <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {['Source', 'Type', 'Knowledge base', 'Documents', 'Chunks', 'Status', 'Last synced'].map((h) => (
                                            <th key={h} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {sources.data.map((source) => (
                                        <tr key={source.uuid} className="hover:bg-slate-50">
                                            <td className="px-4 py-3">
                                                <Link href={route('tenant.admin.knowledge.sources.show', source.uuid)} className="font-medium text-slate-900 hover:text-brand-700">
                                                    {source.name}
                                                </Link>
                                                {source.last_error && <p className="mt-0.5 line-clamp-1 text-xs text-rose-600">{source.last_error}</p>}
                                            </td>
                                            <td className="px-4 py-3"><SourceTypeBadge type={source.source_type} /></td>
                                            <td className="px-4 py-3 text-slate-600">{source.knowledge_base?.name}</td>
                                            <td className="px-4 py-3 text-slate-600">{source.document_count}</td>
                                            <td className="px-4 py-3 text-slate-600">{source.chunk_count}</td>
                                            <td className="px-4 py-3"><KnowledgeStatusBadge status={source.status} /></td>
                                            <td className="px-4 py-3 text-xs text-slate-500">
                                                {source.last_successful_sync_at ? new Date(source.last_successful_sync_at).toLocaleString() : 'Never'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <Pagination links={sources.links} />
                </>
            )}
        </KnowledgeShell>
    );
}
