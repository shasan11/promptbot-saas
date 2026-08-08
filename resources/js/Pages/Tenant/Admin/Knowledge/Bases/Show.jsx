import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import SourceTypeBadge from '@/Components/Knowledge/SourceTypeBadge';
import StatCard from '@/Components/Superadmin/StatCard';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Tabs from '@/Components/UI/Tabs';
import { Link, router } from '@inertiajs/react';
import { Database, FlaskConical, Layers, RefreshCw, Settings, Upload } from 'lucide-react';
import { useState } from 'react';

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

export default function KnowledgeBaseShow({ base, collections, sources, grants, analytics, coverage, can }) {
    const [tab, setTab] = useState('overview');

    return (
        <KnowledgeShell
            title={base.name}
            description={base.description}
            actions={(
                <>
                    <Link
                        href={route('tenant.admin.knowledge.documents.index', { knowledge_base: base.uuid })}
                        className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        <Upload className="h-4 w-4" aria-hidden="true" />
                        Add knowledge
                    </Link>
                    {can?.testRetrieval && (
                        <Link
                            href={route('tenant.admin.knowledge.playground.index')}
                            className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <FlaskConical className="h-4 w-4" aria-hidden="true" />
                            Test retrieval
                        </Link>
                    )}
                    {can?.reindex && (
                        <button
                            type="button"
                            onClick={() => router.post(route('tenant.admin.knowledge.bases.reindex', base.uuid))}
                            className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <RefreshCw className="h-4 w-4" aria-hidden="true" />
                            Re-index
                        </button>
                    )}
                    {can?.update && (
                        <Link
                            href={route('tenant.admin.knowledge.bases.edit', base.uuid)}
                            className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800"
                        >
                            <Settings className="h-4 w-4" aria-hidden="true" />
                            Settings
                        </Link>
                    )}
                </>
            )}
        >
            <div className="mb-5 flex flex-wrap items-center gap-3">
                <KnowledgeStatusBadge status={base.status} />
                <span className="text-xs text-slate-500">
                    {base.embedding_provider === 'local' ? 'Built-in search engine' : `${base.embedding_provider} · ${base.embedding_model}`}
                    {' · '}{base.retrieval_mode} search
                </span>
            </div>

            <Tabs
                items={[
                    { value: 'overview', label: 'Overview' },
                    { value: 'sources', label: 'Sources', count: sources.length },
                    { value: 'collections', label: 'Collections', count: collections.length },
                    { value: 'access', label: 'Access', count: grants.length },
                ]}
                active={tab}
                onChange={setTab}
            />

            <div className="mt-5">
                {tab === 'overview' && (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <StatCard title="Sources" value={base.source_count} icon={Database} tone="slate" />
                            <StatCard title="Documents" value={base.document_count} icon={Layers} tone="blue" />
                            <StatCard title="Chunks" value={base.chunk_count.toLocaleString()} icon={Layers} tone="emerald" />
                            <StatCard title="Storage" value={formatBytes(base.storage_bytes)} icon={Database} tone="slate" />
                        </div>

                        <div className="mt-6 grid gap-6 lg:grid-cols-2">
                            <SectionCard title="Retrieval performance" description="Last 30 days.">
                                <dl className="grid grid-cols-2 gap-4">
                                    {[
                                        ['Searches', analytics.totals.searches ?? 0],
                                        ['Success rate', analytics.totals.success_rate === null ? 'No data' : `${analytics.totals.success_rate}%`],
                                        ['Unanswered', analytics.totals.zero_results ?? 0],
                                        ['Average latency', analytics.totals.average_latency_ms ? `${analytics.totals.average_latency_ms}ms` : '—'],
                                    ].map(([label, value]) => (
                                        <div key={label}>
                                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</dt>
                                            <dd className="mt-0.5 text-xl font-bold text-slate-900">{value}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </SectionCard>

                            <SectionCard title="Knowledge coverage" description="Topics based on how your sources are tagged.">
                                {coverage?.length ? (
                                    <ul className="space-y-2">
                                        {coverage.map((topic) => (
                                            <li key={topic.label} className="flex items-center justify-between text-sm">
                                                <span className="text-slate-700">{topic.label}</span>
                                                <span className="font-semibold text-slate-900">{topic.documents}</span>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-sm text-slate-500">
                                        Tag your sources to see which topics this knowledge base covers.
                                    </p>
                                )}
                            </SectionCard>
                        </div>
                    </>
                )}

                {tab === 'sources' && (
                    sources.length ? (
                        <ul className="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                            {sources.map((source) => (
                                <li key={source.uuid} className="flex items-center justify-between gap-3 px-4 py-3">
                                    <div className="min-w-0">
                                        <Link href={route('tenant.admin.knowledge.sources.show', source.uuid)} className="font-medium text-slate-900 hover:text-brand-700">
                                            {source.name}
                                        </Link>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {source.document_count} documents · {source.chunk_count} chunks
                                            {source.collection ? ` · ${source.collection.name}` : ''}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <SourceTypeBadge type={source.source_type} />
                                        <KnowledgeStatusBadge status={source.status} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState
                            icon={Database}
                            title="No sources yet"
                            description="Upload documents, index a website or write FAQs to give this knowledge base something to answer from."
                            action={(
                                <Link href={route('tenant.admin.knowledge.documents.index', { knowledge_base: base.uuid })} className="rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">
                                    Add knowledge
                                </Link>
                            )}
                        />
                    )
                )}

                {tab === 'collections' && (
                    collections.length ? (
                        <ul className="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                            {collections.map((collection) => (
                                <li key={collection.uuid} className="px-4 py-3" style={{ paddingLeft: `${1 + collection.depth * 1.25}rem` }}>
                                    <p className="text-sm font-medium text-slate-800">{collection.name}</p>
                                    {collection.description && <p className="mt-0.5 text-xs text-slate-500">{collection.description}</p>}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState
                            icon={Layers}
                            title="No collections"
                            description="Collections group related documents inside a knowledge base, and can be used to scope what an agent may read."
                            action={(
                                <Link href={route('tenant.admin.knowledge.collections.index', { knowledge_base: base.uuid })} className="rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">
                                    Manage collections
                                </Link>
                            )}
                        />
                    )
                )}

                {tab === 'access' && (
                    <SectionCard
                        title="Who can use this knowledge"
                        description={base.visibility === 'workspace'
                            ? 'Everyone in this workspace with knowledge permissions can read it. AI agents still need an explicit grant.'
                            : 'Only the people, teams and agents listed below can read it.'}
                    >
                        {grants.length ? (
                            <ul className="divide-y divide-slate-100">
                                {grants.map((grant) => (
                                    <li key={grant.uuid} className="flex items-center justify-between gap-3 py-2.5 first:pt-0">
                                        <div>
                                            <p className="text-sm font-medium text-slate-800">
                                                {grant.grantee_label || grant.grantee_key || `#${grant.grantee_id}`}
                                            </p>
                                            <p className="text-xs capitalize text-slate-500">{grant.grantee_type}</p>
                                        </div>
                                        <span className="text-xs font-semibold capitalize text-slate-600">{grant.access_level}</span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-slate-500">
                                No explicit grants. {base.visibility === 'workspace'
                                    ? 'This knowledge base is readable workspace-wide, but no AI agent can use it until you grant one access.'
                                    : 'Nobody can read this knowledge base yet.'}
                            </p>
                        )}
                    </SectionCard>
                )}
            </div>
        </KnowledgeShell>
    );
}
