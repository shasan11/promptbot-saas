import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import Pagination from '@/Components/Superadmin/Pagination';
import EmptyState from '@/Components/UI/EmptyState';
import { FilterBar } from '@/Components/UI/FilterBar';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import { Link, router } from '@inertiajs/react';
import { Library, Plus } from 'lucide-react';
import { useState } from 'react';

export default function KnowledgeBasesIndex({ bases, filters, statuses, languages, can }) {
    const [search, setSearch] = useState(filters.search || '');

    const applyFilter = (changes) => {
        router.get(
            route('tenant.admin.knowledge.bases.index'),
            { ...filters, ...changes },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    return (
        <KnowledgeShell
            title="Knowledge bases"
            description="Each knowledge base is a container of related information, with its own retrieval settings and access rules."
            actions={can?.create && (
                <Link
                    href={route('tenant.admin.knowledge.bases.create')}
                    className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800"
                >
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    Create knowledge base
                </Link>
            )}
        >
            <FilterBar className="mb-4">
                <form
                    onSubmit={(event) => { event.preventDefault(); applyFilter({ search }); }}
                    className="min-w-[16rem] flex-1"
                >
                    {/* SearchInput hands back the value, not the event. */}
                    <SearchInput
                        value={search}
                        onChange={setSearch}
                        onClear={() => { setSearch(''); applyFilter({ search: '' }); }}
                        placeholder="Search by name, description or tag"
                    />
                </form>

                <div className="w-44">
                    <label htmlFor="kb-status" className="sr-only">Filter by status</label>
                    <Select id="kb-status" value={filters.status || ''} onChange={(event) => applyFilter({ status: event.target.value })}>
                        <option value="">All statuses</option>
                        {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                    </Select>
                </div>

                <div className="w-44">
                    <label htmlFor="kb-language" className="sr-only">Filter by language</label>
                    <Select id="kb-language" value={filters.language || ''} onChange={(event) => applyFilter({ language: event.target.value })}>
                        <option value="">All languages</option>
                        {Object.entries(languages).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                    </Select>
                </div>
            </FilterBar>

            {bases.data.length === 0 ? (
                <EmptyState
                    icon={Library}
                    title={filters.search || filters.status ? 'No knowledge bases match those filters' : 'No knowledge bases yet'}
                    description={filters.search || filters.status
                        ? 'Try clearing the filters to see everything you have access to.'
                        : 'Create your first knowledge base to give your AI agents reliable information.'}
                    action={can?.create && !filters.search && !filters.status && (
                        <Link
                            href={route('tenant.admin.knowledge.bases.create')}
                            className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800"
                        >
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            Create knowledge base
                        </Link>
                    )}
                />
            ) : (
                <>
                    {/* Twelve columns do not fit a phone. Below `md` the table
                        becomes cards rather than a horizontal scroll nobody finds. */}
                    <div className="hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm md:block">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    {['Knowledge base', 'Sources', 'Documents', 'Chunks', 'Access', 'Status', 'Last updated'].map((heading) => (
                                        <th key={heading} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {bases.data.map((base) => (
                                    <tr key={base.uuid} className="hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <Link href={route('tenant.admin.knowledge.bases.show', base.uuid)} className="font-semibold text-slate-900 hover:text-brand-700">
                                                {base.name}
                                            </Link>
                                            {base.description && <p className="mt-0.5 line-clamp-1 max-w-md text-xs text-slate-500">{base.description}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{base.source_count}</td>
                                        <td className="px-4 py-3 text-slate-600">{base.document_count}</td>
                                        <td className="px-4 py-3 text-slate-600">{base.chunk_count.toLocaleString()}</td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {base.visibility === 'workspace' ? 'Workspace' : `${base.access_grants_count} grant(s)`}
                                        </td>
                                        <td className="px-4 py-3"><KnowledgeStatusBadge status={base.status} /></td>
                                        <td className="px-4 py-3 text-xs text-slate-500">
                                            {new Date(base.updated_at).toLocaleDateString()}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <ul className="space-y-3 md:hidden">
                        {bases.data.map((base) => (
                            <li key={base.uuid} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                <div className="flex items-start justify-between gap-3">
                                    <Link href={route('tenant.admin.knowledge.bases.show', base.uuid)} className="font-semibold text-slate-900">
                                        {base.name}
                                    </Link>
                                    <KnowledgeStatusBadge status={base.status} />
                                </div>
                                <p className="mt-2 text-xs text-slate-500">
                                    {base.source_count} sources · {base.document_count} documents · {base.chunk_count.toLocaleString()} chunks
                                </p>
                            </li>
                        ))}
                    </ul>

                    <Pagination links={bases.links} />
                </>
            )}
        </KnowledgeShell>
    );
}
