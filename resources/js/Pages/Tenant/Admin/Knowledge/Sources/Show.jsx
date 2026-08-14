import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import SourceTypeBadge from '@/Components/Knowledge/SourceTypeBadge';
import { SectionCard } from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import { Link, router } from '@inertiajs/react';
import { AlertTriangle, Ban, Play, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

const FRESHNESS_COPY = {
    current: { label: 'Current', className: 'text-emerald-700' },
    potentially_outdated: { label: 'Review due soon', className: 'text-amber-700' },
    outdated: { label: 'Outdated — review this', className: 'text-amber-800' },
    disconnected: { label: 'Disconnected', className: 'text-rose-700' },
};

/**
 * Source detail. Laid out to answer, in order, the questions an operator has
 * when they open it: what is this, is it healthy, when did it last update,
 * how much knowledge did it produce, and what went wrong.
 */
export default function SourceShow({ source, freshness, documents, pages, syncRuns, failures, credential, can }) {
    const fresh = FRESHNESS_COPY[freshness] || FRESHNESS_COPY.current;
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <KnowledgeShell
            title={source.name}
            description={source.description || source.configuration?.url}
            actions={(
                <>
                    {can?.sync && (
                        <button
                            type="button"
                            onClick={() => router.post(route('tenant.admin.knowledge.sources.sync', source.uuid))}
                            className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <RefreshCw className="h-4 w-4" aria-hidden="true" />
                            Sync now
                        </button>
                    )}
                    {can?.update && (
                        <button
                            type="button"
                            onClick={() => router.post(route(
                                source.status === 'disabled'
                                    ? 'tenant.admin.knowledge.sources.enable'
                                    : 'tenant.admin.knowledge.sources.disable',
                                source.uuid
                            ))}
                            className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            {source.status === 'disabled' ? <Play className="h-4 w-4" aria-hidden="true" /> : <Ban className="h-4 w-4" aria-hidden="true" />}
                            {source.status === 'disabled' ? 'Enable' : 'Disable'}
                        </button>
                    )}
                    {can?.delete && (
                        <button
                            type="button"
                            onClick={() => setDeleteOpen(true)}
                            className="inline-flex items-center gap-1.5 rounded-md border border-rose-300 bg-white px-3 py-2 text-sm font-semibold text-rose-700 shadow-sm hover:bg-rose-50"
                        >
                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                            Delete
                        </button>
                    )}
                </>
            )}
        >
            <ConfirmDialog open={deleteOpen} title={`Delete ${source.name}?`} confirmLabel="Delete source" variant="danger" onCancel={() => setDeleteOpen(false)} onConfirm={() => router.delete(route('tenant.admin.knowledge.sources.destroy', source.uuid), { onFinish: () => setDeleteOpen(false) })}>
                Its {source.chunk_count} knowledge chunk(s) will stop being used for AI answers immediately. This action cannot be undone.
            </ConfirmDialog>
            {source.last_error && (
                <div className="mb-5 flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4" role="alert">
                    <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />
                    <div>
                        <p className="text-sm font-semibold text-amber-900">This source needs attention</p>
                        <p className="mt-1 text-sm text-amber-800">{source.last_error}</p>
                    </div>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
                <div className="min-w-0 space-y-6">
                    {documents.length > 0 && (
                        <SectionCard title={`Documents (${documents.length})`}>
                            <ul className="divide-y divide-slate-100">
                                {documents.map((document) => (
                                    <li key={document.uuid} className="flex items-center justify-between gap-3 py-2.5 first:pt-0">
                                        <Link href={route('tenant.admin.knowledge.documents.show', document.uuid)} className="min-w-0 flex-1 truncate text-sm text-slate-800 hover:text-brand-700">
                                            {document.title}
                                        </Link>
                                        <span className="shrink-0 text-xs text-slate-400">{document.chunk_count} chunks</span>
                                        <KnowledgeStatusBadge status={document.status} />
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    )}

                    {pages.length > 0 && (
                        <SectionCard title={`Crawled pages (${pages.length})`}>
                            <ul className="divide-y divide-slate-100">
                                {pages.map((page) => (
                                    <li key={page.uuid} className="flex items-center justify-between gap-3 py-2.5 first:pt-0">
                                        <span className="min-w-0 flex-1 truncate text-sm text-slate-800">{page.page_title || page.url}</span>
                                        <KnowledgeStatusBadge status={page.status} />
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    )}

                    {syncRuns.length > 0 && (
                        <SectionCard title="Synchronisation history">
                            <ul className="divide-y divide-slate-100">
                                {syncRuns.map((run) => (
                                    <li key={run.uuid} className="py-3 first:pt-0">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-sm text-slate-700">
                                                {new Date(run.created_at).toLocaleString()} · {run.trigger}
                                            </span>
                                            <KnowledgeStatusBadge status={run.status} />
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {run.items_discovered} found · {run.items_created} new · {run.items_updated} changed
                                            {' · '}{run.items_unchanged} unchanged (skipped, no cost)
                                            {run.items_failed ? ` · ${run.items_failed} failed` : ''}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    )}

                    {failures.length > 0 && (
                        <SectionCard title="Recent failures">
                            <ul className="space-y-2">
                                {failures.map((failure) => (
                                    <li key={failure.uuid} className="text-sm">
                                        <span className="font-medium text-rose-700">{failure.category.replaceAll('_', ' ')}</span>
                                        <span className="text-slate-600"> — {failure.message}</span>
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    )}
                </div>

                <div className="space-y-4">
                    <SectionCard title="Health">
                        <div className="flex flex-wrap items-center gap-2">
                            <KnowledgeStatusBadge status={source.status} />
                            <SourceTypeBadge type={source.source_type} />
                        </div>
                        <p className={`mt-3 text-sm font-medium ${fresh.className}`}>{fresh.label}</p>
                    </SectionCard>

                    <SectionCard title="Details">
                        <dl className="space-y-3 text-sm">
                            {[
                                ['Knowledge base', source.knowledge_base?.name],
                                ['Collection', source.collection?.name || 'None'],
                                ['Documents', source.document_count],
                                ['Pages', source.page_count],
                                ['Chunks', source.chunk_count],
                                ['Priority', source.priority],
                                ['Sync frequency', source.sync_frequency?.replaceAll('_', ' ')],
                                ['Last synced', source.last_successful_sync_at ? new Date(source.last_successful_sync_at).toLocaleString() : 'Never'],
                                ['Next sync', source.next_sync_at ? new Date(source.next_sync_at).toLocaleString() : 'Not scheduled'],
                                ['Added by', source.creator?.name || '—'],
                            ].map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</dt>
                                    <dd className="mt-0.5 capitalize text-slate-700">{value ?? '—'}</dd>
                                </div>
                            ))}
                        </dl>
                    </SectionCard>

                    {credential && (
                        <SectionCard title="Connected account">
                            {/* Never the secret itself — only its provider, label and state. */}
                            <dl className="space-y-2 text-sm">
                                <div><dt className="text-xs uppercase text-slate-400">Provider</dt><dd className="text-slate-700">{credential.provider}</dd></div>
                                <div><dt className="text-xs uppercase text-slate-400">Account</dt><dd className="text-slate-700">{credential.account || '—'}</dd></div>
                                <div><dt className="text-xs uppercase text-slate-400">Status</dt><dd className="capitalize text-slate-700">{credential.status}</dd></div>
                            </dl>
                        </SectionCard>
                    )}
                </div>
            </div>
        </KnowledgeShell>
    );
}
