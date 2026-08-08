import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import { Link } from '@inertiajs/react';
import { FlaskConical, Loader2, Search } from 'lucide-react';
import { useState } from 'react';

/**
 * The Retrieval Playground.
 *
 * Runs a query through exactly the same permission-filtered retrieval path an
 * AI agent uses, and shows what would be handed to the model — before anything
 * is connected to a customer conversation.
 */
export default function RetrievalPlayground({ bases, collections, modes, defaults, canDebug }) {
    const [query, setQuery] = useState('');
    const [selectedBase, setSelectedBase] = useState('');
    const [collection, setCollection] = useState('');
    const [mode, setMode] = useState(defaults.mode);
    const [topK, setTopK] = useState(defaults.top_k);
    const [threshold, setThreshold] = useState(defaults.similarity_threshold);
    const [rerank, setRerank] = useState(Boolean(defaults.rerank));
    const [generateAnswer, setGenerateAnswer] = useState(false);
    const [debug, setDebug] = useState(false);

    const [running, setRunning] = useState(false);
    const [result, setResult] = useState(null);
    const [error, setError] = useState(null);

    const availableCollections = collections.filter(
        (item) => !selectedBase || String(item.knowledge_base_id) === String(bases.find((b) => b.uuid === selectedBase)?.id)
    );

    const run = async (event) => {
        event.preventDefault();

        if (!query.trim()) return;

        setRunning(true);
        setError(null);

        try {
            const response = await fetch(route('tenant.admin.knowledge.playground.retrieve'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    query,
                    knowledge_bases: selectedBase ? [selectedBase] : [],
                    collection_ids: collection ? [Number(collection)] : [],
                    mode,
                    top_k: Number(topK),
                    similarity_threshold: Number(threshold),
                    rerank,
                    generate_answer: generateAnswer,
                    debug: canDebug && debug,
                }),
            });

            const payload = await response.json();

            if (!response.ok) {
                setError(payload.message || 'Retrieval failed. Try again.');
                setResult(null);
                return;
            }

            setResult(payload);
        } catch {
            setError('Could not reach the server. Check your connection and try again.');
        } finally {
            setRunning(false);
        }
    };

    if (!bases.length) {
        return (
            <KnowledgeShell title="Retrieval playground" description="Test what your AI agents would find, before connecting them to customers.">
                <EmptyState
                    icon={FlaskConical}
                    title="No knowledge bases to test against"
                    description="Create a knowledge base and add at least one source, then come back here to see exactly what your agents would retrieve."
                    action={(
                        <Link href={route('tenant.admin.knowledge.bases.index')} className="rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">
                            Go to knowledge bases
                        </Link>
                    )}
                />
            </KnowledgeShell>
        );
    }

    return (
        <KnowledgeShell
            title="Retrieval playground"
            description="Ask a question the way a customer would, and see exactly which knowledge your agents would use to answer it."
        >
            <div className="grid gap-6 lg:grid-cols-[320px_1fr]">
                <SectionCard title="Query">
                    <form onSubmit={run} className="space-y-4">
                        <FormField label="Question" required id="pg-query">
                            <textarea
                                id="pg-query"
                                rows={3}
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="How long is our refund period?"
                                className="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-soft placeholder:text-slate-400 focus:border-navy-800 focus:outline-none focus:ring-2 focus:ring-navy-800"
                            />
                        </FormField>

                        <FormField label="Knowledge base" id="pg-base" hint="Leave blank to search everything you can access.">
                            <Select id="pg-base" value={selectedBase} onChange={(event) => { setSelectedBase(event.target.value); setCollection(''); }}>
                                <option value="">All knowledge bases</option>
                                {bases.map((base) => <option key={base.uuid} value={base.uuid}>{base.name}</option>)}
                            </Select>
                        </FormField>

                        {availableCollections.length > 0 && (
                            <FormField label="Collection" id="pg-collection">
                                <Select id="pg-collection" value={collection} onChange={(event) => setCollection(event.target.value)}>
                                    <option value="">All collections</option>
                                    {availableCollections.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                                </Select>
                            </FormField>
                        )}

                        <FormField label="Search mode" id="pg-mode" hint={modes.find((m) => m.value === mode)?.description}>
                            <Select id="pg-mode" value={mode} onChange={(event) => setMode(event.target.value)}>
                                {modes.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                            </Select>
                        </FormField>

                        <div className="grid grid-cols-2 gap-3">
                            <FormField label="Results" id="pg-topk">
                                <input
                                    id="pg-topk"
                                    type="number"
                                    min="1"
                                    max="50"
                                    value={topK}
                                    onChange={(event) => setTopK(event.target.value)}
                                    className="block h-10 w-full rounded-md border border-slate-300 px-3 text-sm shadow-soft focus:border-navy-800 focus:outline-none focus:ring-2 focus:ring-navy-800"
                                />
                            </FormField>
                            <FormField label="Min. score" id="pg-threshold">
                                <input
                                    id="pg-threshold"
                                    type="number"
                                    step="0.05"
                                    min="0"
                                    max="1"
                                    value={threshold}
                                    onChange={(event) => setThreshold(event.target.value)}
                                    className="block h-10 w-full rounded-md border border-slate-300 px-3 text-sm shadow-soft focus:border-navy-800 focus:outline-none focus:ring-2 focus:ring-navy-800"
                                />
                            </FormField>
                        </div>

                        <div className="space-y-2">
                            <Switch checked={rerank} onChange={setRerank} label="Re-rank results" />
                            <Switch checked={generateAnswer} onChange={setGenerateAnswer} label="Preview grounded answer" />
                            {canDebug && <Switch checked={debug} onChange={setDebug} label="Show retrieval debug detail" />}
                        </div>

                        <button
                            type="submit"
                            disabled={running || !query.trim()}
                            className="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {running ? <Loader2 className="h-4 w-4 animate-spin motion-reduce:animate-none" aria-hidden="true" /> : <Search className="h-4 w-4" aria-hidden="true" />}
                            {running ? 'Searching…' : 'Run retrieval'}
                        </button>
                    </form>
                </SectionCard>

                <div className="min-w-0 space-y-4">
                    {error && (
                        <div className="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert">{error}</div>
                    )}

                    {!result && !error && (
                        <EmptyState
                            icon={FlaskConical}
                            title="Nothing tested yet"
                            description="Ask a question on the left to see which chunks your agents would retrieve, how confident the match is, and which source it came from."
                        />
                    )}

                    {result && (
                        <>
                            <div className="flex flex-wrap items-center gap-x-5 gap-y-1 rounded-lg border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600 shadow-sm">
                                <span><strong className="text-slate-900">{result.results.length}</strong> result(s)</span>
                                <span><strong className="text-slate-900">{result.timings?.total_ms ?? 0}ms</strong> total</span>
                                {result.timings?.embedding_ms != null && <span>{result.timings.embedding_ms}ms embedding</span>}
                                {result.timings?.semantic_ms != null && <span>{result.timings.semantic_ms}ms semantic</span>}
                                {result.timings?.keyword_ms != null && <span>{result.timings.keyword_ms}ms keyword</span>}
                                <span>{result.context_tokens} context tokens</span>
                            </div>

                            {result.zero_results ? (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                    <p className="font-semibold">No knowledge matched this question.</p>
                                    <p className="mt-1">
                                        An agent asked this would have nothing to ground its answer on. This has been recorded as a
                                        knowledge gap — consider adding an FAQ or a document that covers it.
                                    </p>
                                </div>
                            ) : (
                                <>
                                    {result.answer_preview && (
                                        <SectionCard title="Answer preview" description={result.answer_preview.note}>
                                            <p className="text-sm leading-6 text-slate-700">{result.answer_preview.answer}</p>
                                            <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Confidence: {result.answer_preview.confidence}
                                            </p>
                                        </SectionCard>
                                    )}
                                    <ol className="space-y-3">
                                        {result.results.map((hit) => (
                                            <li key={hit.chunk_uuid} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                            <div className="flex items-start justify-between gap-3">
                                                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-navy-900 text-xs font-bold text-white">
                                                    {hit.rank}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="whitespace-pre-wrap text-sm text-slate-700">{hit.content}</p>

                                                    <dl className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                                        {hit.citation.document_title && (
                                                            <div><dt className="inline font-medium text-slate-600">Source: </dt><dd className="inline">{hit.citation.document_title}</dd></div>
                                                        )}
                                                        {hit.citation.page && (
                                                            <div><dt className="inline font-medium text-slate-600">Page: </dt><dd className="inline">{hit.citation.page}</dd></div>
                                                        )}
                                                        {hit.citation.section && (
                                                            <div><dt className="inline font-medium text-slate-600">Section: </dt><dd className="inline">{hit.citation.section}</dd></div>
                                                        )}
                                                        {hit.citation.url && (
                                                            <div className="min-w-0">
                                                                <dt className="inline font-medium text-slate-600">URL: </dt>
                                                                <dd className="inline break-all">{hit.citation.url}</dd>
                                                            </div>
                                                        )}
                                                    </dl>
                                                </div>
                                                <span className="shrink-0 rounded-md bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">
                                                    {hit.score.toFixed(3)}
                                                </span>
                                            </div>
                                            </li>
                                        ))}
                                    </ol>
                                </>
                            )}

                            {result.debug && (
                                <SectionCard title="Retrieval debug" description="Visible to knowledge administrators only.">
                                    <dl className="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                        {[
                                            ['Semantic candidates', result.debug.semantic_candidates],
                                            ['Keyword candidates', result.debug.keyword_candidates],
                                            ['Threshold', result.debug.similarity_threshold],
                                            ['Knowledge bases searched', result.debug.scoped_knowledge_bases],
                                        ].map(([label, value]) => (
                                            <div key={label}>
                                                <dt className="font-semibold uppercase tracking-wide text-slate-400">{label}</dt>
                                                <dd className="mt-0.5 text-sm text-slate-800">{value}</dd>
                                            </div>
                                        ))}
                                    </dl>

                                    {result.debug.discarded?.length > 0 && (
                                        <div className="mt-4">
                                            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400">Discarded candidates</h3>
                                            <ul className="mt-2 space-y-1.5">
                                                {result.debug.discarded.map((hit) => (
                                                    <li key={hit.chunk_uuid} className="flex items-baseline justify-between gap-3 text-xs">
                                                        <span className="min-w-0 flex-1 truncate text-slate-600">{hit.content}</span>
                                                        <span className="shrink-0 text-slate-400">
                                                            {hit.score.toFixed(3)} — {hit.exclusion_reason?.replaceAll('_', ' ')}
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}

                                    <details className="mt-4">
                                        <summary className="cursor-pointer text-xs font-semibold text-slate-600">Context sent to the model</summary>
                                        <pre className="mt-2 max-h-96 overflow-auto rounded-md bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100">
                                            {result.debug.context}
                                        </pre>
                                    </details>
                                </SectionCard>
                            )}
                        </>
                    )}
                </div>
            </div>
        </KnowledgeShell>
    );
}
