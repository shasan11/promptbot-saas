import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Check, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

const STEPS = [
    { key: 'basics', label: 'Basic information' },
    { key: 'configuration', label: 'How knowledge is processed' },
    { key: 'review', label: 'Review' },
];

/**
 * Knowledge base creation wizard.
 *
 * Step 2 leads with plain-language choices and keeps chunk sizes, thresholds and
 * context budgets behind "Advanced settings" — most people setting up a support
 * knowledge base should never need to know what a chunk is, and the defaults are
 * chosen to work without tuning.
 */
export default function CreateKnowledgeBase({
    languages, visibilities, chunkingStrategies, retrievalModes, embeddingProviders, defaults,
}) {
    const [step, setStep] = useState(0);
    const [showAdvanced, setShowAdvanced] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        default_language: 'en',
        visibility: 'workspace',
        embedding_provider: embeddingProviders.find((provider) => provider.configured)?.key || embeddingProviders[0]?.key,
        chunking_strategy: 'heading',
        chunk_size: defaults.chunk_size,
        chunk_overlap: defaults.chunk_overlap,
        retrieval_mode: 'hybrid',
        top_k: defaults.top_k,
        candidate_pool: defaults.candidate_pool,
        similarity_threshold: defaults.similarity_threshold,
        reranking_enabled: true,
        max_context_tokens: defaults.max_context_tokens,
        require_citations: true,
        exclude_expired_content: true,
    });

    const selectedProvider = embeddingProviders.find((provider) => provider.key === data.embedding_provider);
    const canAdvance = step > 0 || data.name.trim().length > 0;

    const submit = (event) => {
        event.preventDefault();
        post(route('tenant.admin.knowledge.bases.store'));
    };

    return (
        <KnowledgeShell
            title="Create a knowledge base"
            description="A knowledge base groups related information and controls how your AI agents search it."
        >
            <ol className="mb-6 flex flex-wrap items-center gap-2" aria-label="Progress">
                {STEPS.map((item, index) => (
                    <li key={item.key} className="flex items-center gap-2">
                        <span
                            className={`flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold ${
                                index < step ? 'bg-emerald-100 text-emerald-700'
                                    : index === step ? 'bg-navy-900 text-white' : 'bg-slate-100 text-slate-400'
                            }`}
                            aria-current={index === step ? 'step' : undefined}
                        >
                            {index < step ? <Check className="h-3.5 w-3.5" aria-hidden="true" /> : index + 1}
                        </span>
                        <span className={`text-sm ${index === step ? 'font-semibold text-slate-900' : 'text-slate-500'}`}>
                            {item.label}
                        </span>
                        {index < STEPS.length - 1 && <ChevronRight className="h-4 w-4 text-slate-300" aria-hidden="true" />}
                    </li>
                ))}
            </ol>

            <form onSubmit={submit}>
                {step === 0 && (
                    <SectionCard title="Basic information">
                        <div className="space-y-5">
                            <FormField label="Name" required error={errors.name} id="kb-name">
                                <Input
                                    id="kb-name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    placeholder="Customer support knowledge"
                                    autoFocus
                                />
                            </FormField>

                            <FormField
                                label="Description"
                                error={errors.description}
                                id="kb-description"
                                hint="What kind of questions should this knowledge answer?"
                            >
                                <Textarea
                                    id="kb-description"
                                    rows={3}
                                    value={data.description}
                                    onChange={(event) => setData('description', event.target.value)}
                                    placeholder="Refund policies, shipping information and product troubleshooting for customer-facing agents."
                                />
                            </FormField>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <FormField label="Default language" error={errors.default_language} id="kb-language">
                                    <Select id="kb-language" value={data.default_language} onChange={(event) => setData('default_language', event.target.value)}>
                                        {Object.entries(languages).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                                    </Select>
                                </FormField>

                                <FormField
                                    label="Who can see this"
                                    error={errors.visibility}
                                    id="kb-visibility"
                                    hint="AI agents never get access automatically — you grant that separately."
                                >
                                    <Select id="kb-visibility" value={data.visibility} onChange={(event) => setData('visibility', event.target.value)}>
                                        {visibilities.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                    </Select>
                                </FormField>
                            </div>
                        </div>
                    </SectionCard>
                )}

                {step === 1 && (
                    <SectionCard title="How knowledge is processed" description="The defaults work well for most knowledge bases.">
                        <div className="space-y-5">
                            <FormField
                                label="Search quality"
                                error={errors.embedding_provider}
                                id="kb-provider"
                                hint={selectedProvider?.description}
                            >
                                <Select id="kb-provider" value={data.embedding_provider} onChange={(event) => setData('embedding_provider', event.target.value)}>
                                    {embeddingProviders.map((provider) => (
                                        <option key={provider.key} value={provider.key} disabled={!provider.configured}>
                                            {provider.label}{provider.configured ? '' : ' — not configured'}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            {selectedProvider?.key === 'local' && (
                                <div className="flex gap-2.5 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                    <p>
                                        The built-in engine works without any setup, but it matches on wording rather than meaning —
                                        it will not connect “refund” with “money back”. Configure an embedding provider in
                                        Knowledge settings for full semantic search, then re-index.
                                    </p>
                                </div>
                            )}

                            <FormField
                                label="How agents search"
                                error={errors.retrieval_mode}
                                id="kb-mode"
                                hint={retrievalModes.find((mode) => mode.value === data.retrieval_mode)?.description}
                            >
                                <Select id="kb-mode" value={data.retrieval_mode} onChange={(event) => setData('retrieval_mode', event.target.value)}>
                                    {retrievalModes.map((mode) => <option key={mode.value} value={mode.value}>{mode.label}</option>)}
                                </Select>
                            </FormField>

                            <div className="border-t border-slate-100 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setShowAdvanced(!showAdvanced)}
                                    aria-expanded={showAdvanced}
                                    className="text-sm font-semibold text-slate-600 hover:text-slate-900"
                                >
                                    {showAdvanced ? 'Hide' : 'Show'} advanced settings
                                </button>

                                {showAdvanced && (
                                    <div className="mt-4 grid gap-5 sm:grid-cols-2">
                                        <FormField
                                            label="How documents are split"
                                            error={errors.chunking_strategy}
                                            id="kb-strategy"
                                            hint={chunkingStrategies.find((s) => s.value === data.chunking_strategy)?.description}
                                        >
                                            <Select id="kb-strategy" value={data.chunking_strategy} onChange={(event) => setData('chunking_strategy', event.target.value)}>
                                                {chunkingStrategies.map((strategy) => <option key={strategy.value} value={strategy.value}>{strategy.label}</option>)}
                                            </Select>
                                        </FormField>

                                        <FormField label="Chunk size (tokens)" error={errors.chunk_size} id="kb-chunk-size">
                                            <Input id="kb-chunk-size" type="number" value={data.chunk_size} onChange={(event) => setData('chunk_size', Number(event.target.value))} />
                                        </FormField>

                                        <FormField label="Chunk overlap (tokens)" error={errors.chunk_overlap} id="kb-overlap" hint="Carried between chunks so facts spanning a boundary survive.">
                                            <Input id="kb-overlap" type="number" value={data.chunk_overlap} onChange={(event) => setData('chunk_overlap', Number(event.target.value))} />
                                        </FormField>

                                        <FormField label="Results per query" error={errors.top_k} id="kb-topk">
                                            <Input id="kb-topk" type="number" value={data.top_k} onChange={(event) => setData('top_k', Number(event.target.value))} />
                                        </FormField>

                                        <FormField label="Minimum similarity" error={errors.similarity_threshold} id="kb-threshold" hint="Results scoring below this are discarded rather than guessed at.">
                                            <Input id="kb-threshold" type="number" step="0.01" min="0" max="1" value={data.similarity_threshold} onChange={(event) => setData('similarity_threshold', Number(event.target.value))} />
                                        </FormField>

                                        <FormField label="Maximum context (tokens)" error={errors.max_context_tokens} id="kb-context">
                                            <Input id="kb-context" type="number" value={data.max_context_tokens} onChange={(event) => setData('max_context_tokens', Number(event.target.value))} />
                                        </FormField>
                                    </div>
                                )}
                            </div>
                        </div>
                    </SectionCard>
                )}

                {step === 2 && (
                    <SectionCard title="Review" description="You can change any of this later.">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            {[
                                ['Name', data.name],
                                ['Description', data.description || '—'],
                                ['Language', languages[data.default_language]],
                                ['Visibility', visibilities.find((v) => v.value === data.visibility)?.label],
                                ['Search engine', selectedProvider?.label],
                                ['Search mode', retrievalModes.find((m) => m.value === data.retrieval_mode)?.label],
                                ['Chunking', chunkingStrategies.find((s) => s.value === data.chunking_strategy)?.label],
                                ['Results per query', data.top_k],
                            ].map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</dt>
                                    <dd className="mt-0.5 text-sm text-slate-800">{value}</dd>
                                </div>
                            ))}
                        </dl>

                        <p className="mt-5 rounded-md bg-slate-50 p-3 text-sm text-slate-600">
                            After creating this, add sources — upload documents, index a website, or write FAQs.
                            Nothing is searchable until at least one source finishes processing.
                        </p>
                    </SectionCard>
                )}

                <div className="mt-6 flex items-center justify-between gap-3">
                    <div>
                        {step > 0 ? (
                            <button
                                type="button"
                                onClick={() => setStep(step - 1)}
                                className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                            >
                                <ChevronLeft className="h-4 w-4" aria-hidden="true" />
                                Back
                            </button>
                        ) : (
                            <Link href={route('tenant.admin.knowledge.bases.index')} className="text-sm font-semibold text-slate-500 hover:text-slate-800">
                                Cancel
                            </Link>
                        )}
                    </div>

                    {step < STEPS.length - 1 ? (
                        <button
                            type="button"
                            disabled={!canAdvance}
                            onClick={() => setStep(step + 1)}
                            className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Continue
                            <ChevronRight className="h-4 w-4" aria-hidden="true" />
                        </button>
                    ) : (
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:opacity-60"
                        >
                            {processing ? 'Creating…' : 'Create knowledge base'}
                        </button>
                    )}
                </div>
            </form>
        </KnowledgeShell>
    );
}
