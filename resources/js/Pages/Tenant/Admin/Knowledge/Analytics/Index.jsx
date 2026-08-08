import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import StatCard from '@/Components/Superadmin/StatCard';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Select from '@/Components/UI/Select';
import { Link, router } from '@inertiajs/react';
import { BarChart3, Clock, Search, TrendingDown } from 'lucide-react';

export default function KnowledgeAnalytics({ analytics, days }) {
    const { totals, top_queries: topQueries, top_documents: topDocuments, unanswered, cost, daily } = analytics;
    const maxSearches = Math.max(1, ...daily.map((d) => Number(d.searches)));

    return (
        <KnowledgeShell
            title="Knowledge analytics"
            description="How your agents are using this knowledge, and where it falls short."
            actions={(
                <div className="w-40">
                    <label htmlFor="an-days" className="sr-only">Time range</label>
                    <Select
                        id="an-days"
                        value={days}
                        onChange={(e) => router.get(route('tenant.admin.knowledge.analytics.index'), { days: e.target.value }, { preserveState: true })}
                    >
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </Select>
                </div>
            )}
        >
            {(totals.searches ?? 0) === 0 ? (
                <EmptyState
                    icon={BarChart3}
                    title="No retrieval activity yet"
                    description="Once your agents start answering questions — or you test queries in the playground — usage, success rates and knowledge gaps appear here."
                    action={(
                        <Link href={route('tenant.admin.knowledge.playground.index')} className="rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">
                            Try the retrieval playground
                        </Link>
                    )}
                />
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard title="Retrieval requests" value={totals.searches.toLocaleString()} icon={Search} tone="slate" />
                        <StatCard
                            title="Success rate"
                            value={totals.success_rate === null ? 'No data' : `${totals.success_rate}%`}
                            icon={BarChart3}
                            tone={totals.success_rate !== null && totals.success_rate < 80 ? 'amber' : 'emerald'}
                        />
                        <StatCard title="Unanswered queries" value={totals.zero_results.toLocaleString()} icon={TrendingDown} tone={totals.zero_results ? 'rose' : 'slate'} />
                        <StatCard title="Average latency" value={totals.average_latency_ms ? `${totals.average_latency_ms}ms` : '—'} icon={Clock} tone="blue" />
                    </div>

                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        <SectionCard title="Retrieval volume" description={`Last ${days} days.`}>
                            {daily.length ? (
                                <div className="flex h-40 items-end gap-1" role="img" aria-label={`Daily retrieval volume over the last ${days} days`}>
                                    {daily.map((day) => (
                                        <div key={day.day} className="group relative flex-1" title={`${day.day}: ${day.searches} searches, ${day.zero_results} unanswered`}>
                                            <div
                                                className="w-full rounded-t bg-brand-500"
                                                style={{ height: `${(Number(day.searches) / maxSearches) * 100}%`, minHeight: '2px' }}
                                            />
                                            {Number(day.zero_results) > 0 && (
                                                <div
                                                    className="absolute bottom-0 w-full rounded-t bg-rose-400"
                                                    style={{ height: `${(Number(day.zero_results) / maxSearches) * 100}%`, minHeight: '2px' }}
                                                />
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : <p className="text-sm text-slate-500">No data in this range.</p>}
                            <p className="mt-3 flex gap-4 text-xs text-slate-500">
                                <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-brand-500" aria-hidden="true" />Searches</span>
                                <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-rose-400" aria-hidden="true" />Unanswered</span>
                            </p>
                        </SectionCard>

                        <SectionCard title="Questions with no answer" description="Turn these into FAQs to close the gap.">
                            {unanswered?.length ? (
                                <ul className="space-y-3">
                                    {unanswered.map((gap) => (
                                        <li key={gap.uuid} className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="text-sm text-slate-800">“{gap.question}”</p>
                                                <p className="mt-0.5 text-xs text-slate-500">
                                                    Asked {gap.occurrences}× · last {new Date(gap.last_seen_at).toLocaleDateString()}
                                                </p>
                                            </div>
                                            <Link
                                                href={route('tenant.admin.knowledge.faqs.index')}
                                                className="shrink-0 text-xs font-semibold text-brand-700 hover:underline"
                                            >
                                                Write FAQ
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            ) : <p className="text-sm text-slate-500">Every question was answered. Nothing to fix.</p>}
                        </SectionCard>

                        <SectionCard title="Most asked">
                            {topQueries?.length ? (
                                <ul className="space-y-2">
                                    {topQueries.slice(0, 10).map((query, index) => (
                                        <li key={index} className="flex items-center justify-between gap-3 text-sm">
                                            <span className="min-w-0 flex-1 truncate text-slate-700">{query.query}</span>
                                            <span className="shrink-0 text-xs text-slate-500">
                                                {query.occurrences}× · {query.average_score ? Number(query.average_score).toFixed(2) : '—'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            ) : <p className="text-sm text-slate-500">No queries yet.</p>}
                        </SectionCard>

                        <SectionCard title="Most used documents">
                            {topDocuments?.length ? (
                                <ul className="space-y-2">
                                    {topDocuments.map((document) => (
                                        <li key={document.uuid} className="flex items-center justify-between gap-3 text-sm">
                                            <Link href={route('tenant.admin.knowledge.documents.show', document.uuid)} className="min-w-0 flex-1 truncate text-slate-700 hover:text-brand-700">
                                                {document.title}
                                            </Link>
                                            <span className="shrink-0 text-xs text-slate-500">{document.retrievals}×</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : <p className="text-sm text-slate-500">No documents have been retrieved yet.</p>}
                        </SectionCard>
                    </div>

                    {cost?.length > 0 && (
                        <SectionCard title="AI usage and cost" className="mt-6">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-wide text-slate-400">
                                        <th scope="col" className="pb-2">Operation</th>
                                        <th scope="col" className="pb-2">Provider</th>
                                        <th scope="col" className="pb-2">Units</th>
                                        <th scope="col" className="pb-2">Requests</th>
                                        <th scope="col" className="pb-2">Estimated cost</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {cost.map((row, index) => (
                                        <tr key={index}>
                                            <td className="py-2 capitalize text-slate-700">{row.operation}</td>
                                            <td className="py-2 text-slate-600">{row.provider}</td>
                                            <td className="py-2 text-slate-600">{Number(row.units).toLocaleString()}</td>
                                            <td className="py-2 text-slate-600">{Number(row.requests).toLocaleString()}</td>
                                            <td className="py-2 font-medium text-slate-800">${Number(row.cost).toFixed(4)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </SectionCard>
                    )}
                </>
            )}
        </KnowledgeShell>
    );
}
