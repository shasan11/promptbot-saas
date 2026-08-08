import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import Pagination from '@/Components/Superadmin/Pagination';
import EmptyState from '@/Components/UI/EmptyState';
import { FilterBar } from '@/Components/UI/FilterBar';
import Select from '@/Components/UI/Select';
import { Link, router } from '@inertiajs/react';
import { CheckCircle2, RotateCw, X } from 'lucide-react';
import { useState } from 'react';

export default function FailedSources({ failures, filters, categories, can }) {
    const [expanded, setExpanded] = useState(null);
    const [details, setDetails] = useState({});

    const loadDetails = async (uuid) => {
        if (expanded === uuid) { setExpanded(null); return; }

        setExpanded(uuid);

        if (details[uuid] || !can?.viewTechnicalDetails) return;

        const response = await fetch(route('tenant.admin.knowledge.failed.details', uuid), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (response.ok) {
            const payload = await response.json();
            setDetails((current) => ({ ...current, [uuid]: payload }));
        }
    };

    return (
        <KnowledgeShell
            title="Failed sources"
            description="Content that could not be processed, with what to do about each one."
        >
            <FilterBar className="mb-4">
                <div className="w-56">
                    <label htmlFor="fail-category" className="sr-only">Filter by error type</label>
                    <Select
                        id="fail-category"
                        value={filters.category || ''}
                        onChange={(event) => router.get(route('tenant.admin.knowledge.failed.index'), { category: event.target.value }, { preserveState: true, replace: true })}
                    >
                        <option value="">All error types</option>
                        {categories.map((category) => <option key={category.value} value={category.value}>{category.label}</option>)}
                    </Select>
                </div>
            </FilterBar>

            {failures.data.length === 0 ? (
                <EmptyState
                    icon={CheckCircle2}
                    title="Everything looks healthy"
                    description="No failed sources. Anything that cannot be processed will appear here with an explanation and a fix."
                />
            ) : (
                <>
                    <ul className="space-y-3">
                        {failures.data.map((failure) => {
                            const category = categories.find((c) => c.value === failure.category);

                            return (
                                <li key={failure.uuid} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                                                    {category?.label || failure.category}
                                                </span>
                                                <span className="text-xs text-slate-400">
                                                    failed at {failure.stage} · attempt {failure.attempt} · {new Date(failure.created_at).toLocaleString()}
                                                </span>
                                            </div>

                                            <p className="mt-2 text-sm font-medium text-slate-900">
                                                {failure.document ? (
                                                    <Link href={route('tenant.admin.knowledge.documents.show', failure.document.uuid)} className="hover:text-brand-700">
                                                        {failure.document.title}
                                                    </Link>
                                                ) : failure.source ? (
                                                    <Link href={route('tenant.admin.knowledge.sources.show', failure.source.uuid)} className="hover:text-brand-700">
                                                        {failure.source.name}
                                                    </Link>
                                                ) : 'Deleted item'}
                                                {failure.knowledge_base && <span className="ml-2 text-xs font-normal text-slate-400">in {failure.knowledge_base.name}</span>}
                                            </p>

                                            {/* The actionable message, never the exception. */}
                                            <p className="mt-1.5 text-sm text-slate-600">{failure.message}</p>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-2">
                                            {can?.retry && (
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(route('tenant.admin.knowledge.failed.retry', failure.uuid), {}, { preserveScroll: true })}
                                                    className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                >
                                                    <RotateCw className="h-3.5 w-3.5" aria-hidden="true" />
                                                    Retry
                                                </button>
                                            )}
                                            {can?.retry && (
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(route('tenant.admin.knowledge.failed.dismiss', failure.uuid), {}, { preserveScroll: true })}
                                                    className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                                >
                                                    <X className="h-3.5 w-3.5" aria-hidden="true" />
                                                    Dismiss
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {can?.viewTechnicalDetails && (
                                        <div className="mt-3 border-t border-slate-100 pt-3">
                                            <button
                                                type="button"
                                                onClick={() => loadDetails(failure.uuid)}
                                                aria-expanded={expanded === failure.uuid}
                                                className="text-xs font-semibold text-slate-500 hover:text-slate-800"
                                            >
                                                {expanded === failure.uuid ? 'Hide' : 'Show'} technical details
                                            </button>

                                            {expanded === failure.uuid && (
                                                <pre className="mt-2 max-h-64 overflow-auto rounded-md bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100">
                                                    {details[failure.uuid]?.technical_details || 'Loading…'}
                                                </pre>
                                            )}
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>

                    <Pagination links={failures.links} />
                </>
            )}
        </KnowledgeShell>
    );
}
