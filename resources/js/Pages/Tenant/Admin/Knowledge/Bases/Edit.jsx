import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import Textarea from '@/Components/UI/Textarea';
import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function EditKnowledgeBase({
    base, languages, visibilities, statuses, chunkingStrategies, retrievalModes, embeddingProviders, staleVectorCount,
}) {
    const [confirmText, setConfirmText] = useState('');

    // Processing/Warning are computed automatically and Archived has its own
    // Archive/Restore action, so this dropdown only ever offers the manually
    // settable states — a base sitting in one of the other states shows its
    // current status read-only instead of a dropdown that can't represent it.
    const statusIsManuallySettable = statuses.some((s) => s.value === base.status);

    const { data, setData, put, processing, errors } = useForm({
        name: base.name,
        description: base.description || '',
        default_language: base.default_language,
        visibility: base.visibility,
        // Only sent when it is a value the request actually accepts — a base
        // currently Processing/Warning/Archived must not have that value
        // bounced back to it as a validation error on an unrelated save.
        ...(statusIsManuallySettable ? { status: base.status } : {}),
        embedding_provider: base.embedding_provider,
        chunking_strategy: base.chunking_strategy,
        chunk_size: base.chunk_size,
        chunk_overlap: base.chunk_overlap,
        retrieval_mode: base.retrieval_mode,
        top_k: base.top_k,
        candidate_pool: base.candidate_pool,
        similarity_threshold: base.similarity_threshold,
        reranking_enabled: base.reranking_enabled,
        max_context_tokens: base.max_context_tokens,
        prefer_recent_content: base.prefer_recent_content,
        require_citations: base.require_citations,
        exclude_expired_content: base.exclude_expired_content,
        review_every_days: base.review_every_days || '',
    });

    const providerChanged = data.embedding_provider !== base.embedding_provider;

    return (
        <KnowledgeShell title={`${base.name} settings`} description="Changes apply to new content immediately. Existing content may need re-indexing.">
            <form onSubmit={(event) => { event.preventDefault(); put(route('tenant.admin.knowledge.bases.update', base.uuid)); }} className="space-y-6">
                <SectionCard title="General">
                    <div className="space-y-5">
                        <FormField label="Name" required id="e-name" error={errors.name}>
                            <Input id="e-name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        </FormField>
                        <FormField label="Description" id="e-desc" error={errors.description}>
                            <Textarea id="e-desc" rows={2} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        </FormField>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField label="Default language" id="e-lang" error={errors.default_language}>
                                <Select id="e-lang" value={data.default_language} onChange={(e) => setData('default_language', e.target.value)}>
                                    {Object.entries(languages).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                                </Select>
                            </FormField>
                            <FormField label="Visibility" id="e-vis" error={errors.visibility}>
                                <Select id="e-vis" value={data.visibility} onChange={(e) => setData('visibility', e.target.value)}>
                                    {visibilities.map((v) => <option key={v.value} value={v.value}>{v.label}</option>)}
                                </Select>
                            </FormField>
                        </div>

                        {statusIsManuallySettable ? (
                            <FormField
                                label="Status"
                                id="e-status"
                                error={errors.status}
                                hint={data.status === 'draft'
                                    ? 'Draft knowledge bases activate automatically once a source finishes processing — set this to Active only if you want agents to use it before that.'
                                    : data.status === 'disabled'
                                        ? 'Disabled removes every chunk in this base from retrieval immediately, without deleting anything.'
                                        : 'Agents can use this knowledge base.'}
                            >
                                <Select id="e-status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                    {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                                </Select>
                            </FormField>
                        ) : (
                            <FormField label="Status" id="e-status-readonly" hint={base.status === 'archived'
                                ? 'This base is archived. Use "Restore" to bring it back before changing its status here.'
                                : 'This status is set automatically based on its sources and cannot be edited directly.'}
                            >
                                <p className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm capitalize text-slate-700">{base.status.replaceAll('_', ' ')}</p>
                            </FormField>
                        )}
                    </div>
                </SectionCard>

                <SectionCard title="Search engine">
                    <FormField label="Embedding provider" id="e-provider" error={errors.embedding_provider}>
                        <Select id="e-provider" value={data.embedding_provider} onChange={(e) => setData('embedding_provider', e.target.value)}>
                            {embeddingProviders.map((p) => (
                                <option key={p.key} value={p.key} disabled={!p.configured}>
                                    {p.label}{p.configured ? '' : ' — not configured'}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    {providerChanged && (
                        <div className="mt-4 flex gap-2.5 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                            <p>
                                Changing the embedding provider makes every existing vector unusable. Saving will start a full
                                re-index of {base.chunk_count.toLocaleString()} chunks. Retrieval returns incomplete results until
                                that finishes.
                            </p>
                        </div>
                    )}

                    {!providerChanged && staleVectorCount > 0 && (
                        <p className="mt-4 text-sm text-amber-700">
                            {staleVectorCount.toLocaleString()} chunk(s) were embedded with a different model and are excluded from
                            semantic search. Re-index this knowledge base to bring them back.
                        </p>
                    )}
                </SectionCard>

                <SectionCard title="Retrieval" description="How your agents search this knowledge base.">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField label="Search mode" id="e-mode" hint={retrievalModes.find((m) => m.value === data.retrieval_mode)?.description}>
                            <Select id="e-mode" value={data.retrieval_mode} onChange={(e) => setData('retrieval_mode', e.target.value)}>
                                {retrievalModes.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                            </Select>
                        </FormField>
                        <FormField label="Results per query" id="e-topk" error={errors.top_k}>
                            <Input id="e-topk" type="number" value={data.top_k} onChange={(e) => setData('top_k', Number(e.target.value))} />
                        </FormField>
                        <FormField label="Candidate pool" id="e-pool" error={errors.candidate_pool} hint="How many results are considered before ranking.">
                            <Input id="e-pool" type="number" value={data.candidate_pool} onChange={(e) => setData('candidate_pool', Number(e.target.value))} />
                        </FormField>
                        <FormField label="Minimum similarity" id="e-thresh" error={errors.similarity_threshold} hint="Below this, results are discarded rather than guessed at.">
                            <Input id="e-thresh" type="number" step="0.01" min="0" max="1" value={data.similarity_threshold} onChange={(e) => setData('similarity_threshold', Number(e.target.value))} />
                        </FormField>
                        <FormField label="Maximum context tokens" id="e-ctx" error={errors.max_context_tokens}>
                            <Input id="e-ctx" type="number" value={data.max_context_tokens} onChange={(e) => setData('max_context_tokens', Number(e.target.value))} />
                        </FormField>
                        <FormField label="Review content every (days)" id="e-review" error={errors.review_every_days} hint="Flags sources for review once they pass this age.">
                            <Input id="e-review" type="number" value={data.review_every_days} onChange={(e) => setData('review_every_days', e.target.value)} />
                        </FormField>
                    </div>

                    <div className="mt-5 space-y-3 border-t border-slate-100 pt-4">
                        <Switch checked={data.reranking_enabled} onChange={(v) => setData('reranking_enabled', v)} label="Re-rank results" description="Improves ordering using term coverage, source priority and exact phrase matches." />
                        <Switch checked={data.prefer_recent_content} onChange={(v) => setData('prefer_recent_content', v)} label="Prefer recent content" description="Nudges newer knowledge ahead of older knowledge of similar relevance." />
                        <Switch checked={data.require_citations} onChange={(v) => setData('require_citations', v)} label="Require citations" description="Answers must point back to the source they came from." />
                        <Switch checked={data.exclude_expired_content} onChange={(v) => setData('exclude_expired_content', v)} label="Exclude expired content" description="Knowledge past its effective date stops answering current questions." />
                    </div>
                </SectionCard>

                <SectionCard title="Chunking" description="Advanced — the defaults suit most knowledge bases.">
                    <div className="grid gap-5 sm:grid-cols-3">
                        <FormField label="Strategy" id="e-strategy" hint={chunkingStrategies.find((s) => s.value === data.chunking_strategy)?.description}>
                            <Select id="e-strategy" value={data.chunking_strategy} onChange={(e) => setData('chunking_strategy', e.target.value)}>
                                {chunkingStrategies.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                            </Select>
                        </FormField>
                        <FormField label="Chunk size" id="e-size" error={errors.chunk_size}>
                            <Input id="e-size" type="number" value={data.chunk_size} onChange={(e) => setData('chunk_size', Number(e.target.value))} />
                        </FormField>
                        <FormField label="Overlap" id="e-overlap" error={errors.chunk_overlap}>
                            <Input id="e-overlap" type="number" value={data.chunk_overlap} onChange={(e) => setData('chunk_overlap', Number(e.target.value))} />
                        </FormField>
                    </div>
                    <p className="mt-3 text-xs text-slate-500">
                        Changing these affects new content only. Use “Re-index” with re-chunking to apply them to existing documents.
                    </p>
                </SectionCard>

                <div className="flex justify-end">
                    <button type="submit" disabled={processing} className="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:opacity-60">
                        {processing ? 'Saving…' : 'Save settings'}
                    </button>
                </div>
            </form>

            <SectionCard title="Danger zone" className="mt-8 border-rose-200">
                <p className="text-sm text-slate-600">
                    Deleting this knowledge base removes {base.document_count} document(s) and {base.chunk_count.toLocaleString()} chunk(s)
                    from every agent that uses it. This takes effect immediately.
                </p>
                <div className="mt-4 flex flex-wrap items-end gap-3">
                    <FormField label={`Type "${base.name}" to confirm`} id="e-confirm" className="min-w-[16rem] flex-1">
                        <Input id="e-confirm" value={confirmText} onChange={(e) => setConfirmText(e.target.value)} />
                    </FormField>
                    <button
                        type="button"
                        disabled={confirmText !== base.name}
                        onClick={() => router.delete(route('tenant.admin.knowledge.bases.destroy', base.uuid))}
                        className="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <Trash2 className="h-4 w-4" aria-hidden="true" />
                        Delete knowledge base
                    </button>
                </div>
            </SectionCard>
        </KnowledgeShell>
    );
}
