import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import ProcessingProgress from '@/Components/Knowledge/ProcessingProgress';
import Pagination from '@/Components/Superadmin/Pagination';
import StatCard from '@/Components/Superadmin/StatCard';
import EmptyState from '@/Components/UI/EmptyState';
import { Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock, Loader2, RotateCw, XCircle } from 'lucide-react';
import { useEffect } from 'react';

function duration(job) {
    const start = job.started_at || job.queued_at;

    if (!start) return '—';

    const end = job.finished_at ? new Date(job.finished_at) : new Date();
    const seconds = Math.max(0, Math.floor((end - new Date(start)) / 1000));

    return seconds < 60 ? `${seconds}s` : `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

export default function ProcessingQueue({ jobs, filters, summary, can }) {
    // Active work changes without user interaction, so the page refreshes
    // itself — but only while something is actually running, and preserving
    // scroll so it does not fight whoever is reading it.
    const hasActiveWork = summary.queued + summary.running + summary.retrying > 0;

    useEffect(() => {
        if (!hasActiveWork) return undefined;

        const timer = setInterval(() => {
            router.reload({ only: ['jobs', 'summary'], preserveScroll: true, preserveState: true });
        }, 5000);

        return () => clearInterval(timer);
    }, [hasActiveWork]);

    return (
        <KnowledgeShell
            title="Processing"
            description="Work currently running against your knowledge — extraction, chunking, embedding and crawling."
            actions={(
                <Link
                    href={route('tenant.admin.knowledge.processing.index', { status: filters.status === 'all' ? '' : 'all' })}
                    className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                >
                    {filters.status === 'all' ? 'Show active only' : 'Show all jobs'}
                </Link>
            )}
        >
            <div className="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Queued" value={summary.queued} icon={Clock} tone="slate" />
                <StatCard title="Running" value={summary.running} icon={Loader2} tone="blue" />
                <StatCard title="Retrying" value={summary.retrying} icon={RotateCw} tone="amber" />
                <StatCard title="Failed today" value={summary.failed_today} icon={XCircle} tone={summary.failed_today ? 'rose' : 'slate'} />
            </div>

            {jobs.data.length === 0 ? (
                <EmptyState
                    icon={CheckCircle2}
                    title="Nothing is processing"
                    description="All your knowledge is up to date. Jobs appear here while documents are being extracted, chunked and embedded."
                />
            ) : (
                <>
                    <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {['Item', 'Knowledge base', 'Type', 'Stage', 'Started', 'Duration', 'Status', ''].map((heading, index) => (
                                            <th key={index} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">{heading}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {jobs.data.map((job) => (
                                        <tr key={job.uuid} className="hover:bg-slate-50">
                                            <td className="px-4 py-3">
                                                {job.document ? (
                                                    <Link href={route('tenant.admin.knowledge.documents.show', job.document.uuid)} className="font-medium text-slate-900 hover:text-brand-700">
                                                        {job.document.title}
                                                    </Link>
                                                ) : job.source ? (
                                                    <Link href={route('tenant.admin.knowledge.sources.show', job.source.uuid)} className="font-medium text-slate-900 hover:text-brand-700">
                                                        {job.source.name}
                                                    </Link>
                                                ) : <span className="text-slate-500">—</span>}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{job.knowledge_base?.name || '—'}</td>
                                            <td className="px-4 py-3 text-xs text-slate-500">{job.job_type.replaceAll('_', ' ')}</td>
                                            <td className="px-4 py-3 min-w-[9rem]">
                                                <ProcessingProgress compact stage={job.current_stage} status={job.status} progress={job.progress} />
                                            </td>
                                            <td className="px-4 py-3 text-xs text-slate-500">
                                                {job.started_at ? new Date(job.started_at).toLocaleTimeString() : 'Not started'}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-slate-500">{duration(job)}</td>
                                            <td className="px-4 py-3"><KnowledgeStatusBadge status={job.status} /></td>
                                            <td className="px-4 py-3 text-right">
                                                {can?.manage && ['queued', 'running', 'retrying'].includes(job.status) && (
                                                    <button
                                                        type="button"
                                                        onClick={() => router.post(route('tenant.admin.knowledge.processing.cancel', job.uuid), {}, { preserveScroll: true })}
                                                        className="text-xs font-semibold text-rose-700 hover:underline"
                                                    >
                                                        Cancel
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <Pagination links={jobs.links} />
                </>
            )}
        </KnowledgeShell>
    );
}
