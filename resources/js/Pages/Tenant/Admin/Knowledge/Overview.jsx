import HealthSummary from '@/Components/Knowledge/HealthSummary';
import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import StatCard from '@/Components/Superadmin/StatCard';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import { Link } from '@inertiajs/react';
import {
    Boxes, Database, FileText, FlaskConical, HardDrive, Layers, Library, Plus, XCircle,
} from 'lucide-react';

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

function relativeTime(value) {
    if (!value) return 'Never';

    const seconds = Math.floor((Date.now() - new Date(value).getTime()) / 1000);

    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;

    return `${Math.floor(seconds / 86400)} days ago`;
}

export default function KnowledgeOverview({ stats, activity, gaps, bases, can }) {
    return (
        <KnowledgeShell
            title="Knowledge base"
            description="Manage the information your AI agents use to answer questions and perform actions."
            actions={(
                <>
                    {can?.testRetrieval && (
                        <Link
                            href={route('tenant.admin.knowledge.playground.index')}
                            className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <FlaskConical className="h-4 w-4" aria-hidden="true" />
                            Test retrieval
                        </Link>
                    )}
                    {can?.create && (
                        <Link
                            href={route('tenant.admin.knowledge.bases.create')}
                            className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800"
                        >
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            Create knowledge base
                        </Link>
                    )}
                </>
            )}
        >
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Knowledge bases" value={stats.knowledge_bases.toLocaleString()} icon={Library} tone="slate" />
                <StatCard title="Active sources" value={stats.active_sources.toLocaleString()} icon={Database} tone="blue" />
                <StatCard title="Indexed documents" value={stats.indexed_documents.toLocaleString()} icon={FileText} tone="emerald" />
                <StatCard title="Total chunks" value={stats.total_chunks.toLocaleString()} icon={Layers} tone="slate" />
                <StatCard title="Storage used" value={formatBytes(stats.storage_bytes)} icon={HardDrive} tone="slate" />
                <StatCard title="Failed sources" value={stats.failed_sources.toLocaleString()} icon={XCircle} tone={stats.failed_sources > 0 ? 'rose' : 'slate'} />
                <StatCard title="Last synchronisation" value={relativeTime(stats.last_synced_at)} icon={Boxes} tone="slate" />
                <StatCard
                    title="Retrieval success rate"
                    /* Null means no traffic, not perfect performance — say so. */
                    value={stats.retrieval_success_rate === null ? 'No data yet' : `${stats.retrieval_success_rate}%`}
                    icon={FlaskConical}
                    tone={stats.retrieval_success_rate !== null && stats.retrieval_success_rate < 80 ? 'amber' : 'emerald'}
                />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <SectionCard title="Knowledge health" description="Where your sources stand right now." className="lg:col-span-2">
                    <HealthSummary health={stats.health} />
                </SectionCard>

                <SectionCard title="Unanswered questions" description="Questions your knowledge base could not answer.">
                    {gaps?.length ? (
                        <ul className="space-y-3">
                            {gaps.map((gap) => (
                                <li key={gap.uuid} className="text-sm">
                                    <p className="font-medium text-slate-800">“{gap.question}”</p>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        Asked {gap.occurrences}× · {gap.knowledge_base?.name || 'All knowledge bases'}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-slate-500">
                            Nothing yet. Questions your agents cannot answer will be collected here so you can turn them into FAQs.
                        </p>
                    )}
                </SectionCard>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <SectionCard
                    title="Your knowledge bases"
                    actions={<Link href={route('tenant.admin.knowledge.bases.index')} className="text-sm font-semibold text-brand-700 hover:underline">View all</Link>}
                >
                    {bases?.length ? (
                        <ul className="divide-y divide-slate-100">
                            {bases.map((base) => (
                                <li key={base.uuid} className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                    <div className="min-w-0">
                                        <Link
                                            href={route('tenant.admin.knowledge.bases.show', base.uuid)}
                                            className="block truncate text-sm font-semibold text-slate-900 hover:text-brand-700"
                                        >
                                            {base.name}
                                        </Link>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {base.source_count} sources · {base.document_count} documents · {base.chunk_count.toLocaleString()} chunks
                                        </p>
                                    </div>
                                    <KnowledgeStatusBadge status={base.status} />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState
                            icon={Library}
                            title="No knowledge bases yet"
                            description="Create your first knowledge base to give your AI agents reliable information to answer from."
                            action={can?.create && (
                                <Link
                                    href={route('tenant.admin.knowledge.bases.create')}
                                    className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800"
                                >
                                    <Plus className="h-4 w-4" aria-hidden="true" />
                                    Create knowledge base
                                </Link>
                            )}
                        />
                    )}
                </SectionCard>

                <SectionCard title="Recent activity">
                    {activity?.length ? (
                        <ul className="divide-y divide-slate-100">
                            {activity.map((event, index) => (
                                <li key={index} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                                    <span
                                        aria-hidden="true"
                                        className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${
                                            event.level === 'error' ? 'bg-rose-500' : event.level === 'warning' ? 'bg-amber-500' : 'bg-emerald-500'
                                        }`}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm text-slate-700">
                                            {event.entity && <span className="font-medium text-slate-900">{event.entity}</span>}
                                            {event.entity ? ' — ' : ''}{event.message}
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-400">{relativeTime(event.created_at)}</p>
                                    </div>
                                    {event.document_uuid && (
                                        <Link
                                            href={route('tenant.admin.knowledge.documents.show', event.document_uuid)}
                                            className="shrink-0 text-xs font-semibold text-brand-700 hover:underline"
                                        >
                                            View
                                        </Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-slate-500">Nothing has been processed yet.</p>
                    )}
                </SectionCard>
            </div>
        </KnowledgeShell>
    );
}
