import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import { useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

const USAGE_LABELS = {
    knowledge_bases: 'Knowledge bases',
    documents: 'Documents',
    website_pages: 'Website pages',
    chunks: 'Chunks',
    storage_bytes: 'Storage',
    embedding_tokens_per_month: 'Embedding tokens this month',
    crawl_pages_per_month: 'Pages crawled this month',
};

export default function KnowledgeSettings({ settings, embeddingProviders, usage, platformLimits, ocr, vectorStore, languages }) {
    const { data, setData, put, processing, errors } = useForm({ ...settings });

    return (
        <KnowledgeShell
            title="Knowledge settings"
            description="Workspace defaults for new knowledge bases, plus how processing behaves."
        >
            <form onSubmit={(e) => { e.preventDefault(); put(route('tenant.admin.knowledge.settings.update')); }} className="space-y-6">
                <SectionCard title="General">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField label="Default language" id="s-lang" error={errors.default_language}>
                            <Select id="s-lang" value={data.default_language} onChange={(e) => setData('default_language', e.target.value)}>
                                {Object.entries(languages).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                            </Select>
                        </FormField>
                        <FormField label="Review content every (days)" id="s-review" error={errors.default_review_every_days} hint="Sources older than this are flagged for review.">
                            <Input id="s-review" type="number" value={data.default_review_every_days || ''} onChange={(e) => setData('default_review_every_days', e.target.value)} />
                        </FormField>
                    </div>
                </SectionCard>

                <SectionCard title="Search engine" description="Applies to new knowledge bases. Existing ones keep their own setting until re-indexed.">
                    <FormField label="Default embedding provider" id="s-provider" error={errors.default_embedding_provider}>
                        <Select id="s-provider" value={data.default_embedding_provider} onChange={(e) => setData('default_embedding_provider', e.target.value)}>
                            {embeddingProviders.map((p) => (
                                <option key={p.key} value={p.key} disabled={!p.configured}>
                                    {p.label}{p.configured ? '' : ' — no API key configured'}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <div className="mt-4 space-y-2 rounded-md bg-slate-50 p-3 text-xs text-slate-600">
                        {embeddingProviders.map((p) => (
                            <p key={p.key}><strong className="text-slate-800">{p.label}:</strong> {p.description}</p>
                        ))}
                    </div>
                </SectionCard>

                <SectionCard title="Processing defaults">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField label="Chunk size (tokens)" id="s-chunk" error={errors.default_chunk_size} hint={`Between ${platformLimits.chunk_size.min} and ${platformLimits.chunk_size.max}.`}>
                            <Input id="s-chunk" type="number" value={data.default_chunk_size} onChange={(e) => setData('default_chunk_size', Number(e.target.value))} />
                        </FormField>
                        <FormField label="Chunk overlap (tokens)" id="s-overlap" error={errors.default_chunk_overlap}>
                            <Input id="s-overlap" type="number" value={data.default_chunk_overlap} onChange={(e) => setData('default_chunk_overlap', Number(e.target.value))} />
                        </FormField>
                        <FormField label="Results per query" id="s-topk" error={errors.default_top_k} hint={`Up to ${platformLimits.max_top_k}.`}>
                            <Input id="s-topk" type="number" value={data.default_top_k} onChange={(e) => setData('default_top_k', Number(e.target.value))} />
                        </FormField>
                        <FormField label="Minimum similarity" id="s-thresh" error={errors.default_similarity_threshold}>
                            <Input id="s-thresh" type="number" step="0.01" min="0" max="1" value={data.default_similarity_threshold} onChange={(e) => setData('default_similarity_threshold', Number(e.target.value))} />
                        </FormField>
                        <FormField label="Maximum upload size (KB)" id="s-filesize" error={errors.max_file_size_kb} hint={`Platform maximum is ${formatBytes(platformLimits.max_file_size_kb * 1024)}.`}>
                            <Input id="s-filesize" type="number" value={data.max_file_size_kb} onChange={(e) => setData('max_file_size_kb', Number(e.target.value))} />
                        </FormField>
                    </div>

                    <div className="mt-5 space-y-3 border-t border-slate-100 pt-4">
                        <Switch checked={data.auto_retry_failed_sources} onChange={(v) => setData('auto_retry_failed_sources', v)} label="Automatically retry transient failures" description="Timeouts and rate limits retry with backoff. Permanent errors never auto-retry." />
                        <Switch checked={data.notify_on_source_failure} onChange={(v) => setData('notify_on_source_failure', v)} label="Email me when a source stops working" description="One notification per source, not per document." />
                    </div>
                </SectionCard>

                <div className="flex justify-end">
                    <button type="submit" disabled={processing} className="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:opacity-60">
                        {processing ? 'Saving…' : 'Save settings'}
                    </button>
                </div>
            </form>

            <SectionCard title="Usage" className="mt-8">
                <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {Object.entries(usage).map(([key, value]) => (
                        <div key={key}>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">{USAGE_LABELS[key] || key}</dt>
                            <dd className="mt-1">
                                <span className={`text-lg font-bold ${value.exceeded ? 'text-rose-700' : 'text-slate-900'}`}>
                                    {key === 'storage_bytes' ? formatBytes(value.used) : value.used.toLocaleString()}
                                </span>
                                {value.limit !== null && (
                                    <span className="text-xs text-slate-500">
                                        {' / '}{key === 'storage_bytes' ? formatBytes(value.limit) : value.limit.toLocaleString()}
                                    </span>
                                )}
                                {value.percentage !== null && (
                                    <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            className={`h-full rounded-full ${value.percentage > 90 ? 'bg-rose-500' : value.percentage > 70 ? 'bg-amber-500' : 'bg-emerald-500'}`}
                                            style={{ width: `${value.percentage}%` }}
                                        />
                                    </div>
                                )}
                            </dd>
                        </div>
                    ))}
                </dl>
            </SectionCard>

            <SectionCard title="How this installation is configured" className="mt-6">
                <div className="space-y-3 text-sm">
                    <div className="flex gap-2.5">
                        <Info className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                        <p className="text-slate-600">
                            <strong className="text-slate-800">Vector search:</strong> {vectorStore.driver} driver,
                            scanning up to {vectorStore.max_candidates.toLocaleString()} candidates per query.
                            These are platform settings — contact your administrator to change them.
                        </p>
                    </div>
                    <div className="flex gap-2.5">
                        <Info className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                        <p className="text-slate-600">
                            <strong className="text-slate-800">OCR for scanned documents:</strong>{' '}
                            {ocr.available
                                ? `enabled using the ${ocr.provider} provider.`
                                : 'not available on this installation. Scanned PDFs with no embedded text cannot be read, and will be reported as failed with an explanation.'}
                        </p>
                    </div>
                </div>
            </SectionCard>
        </KnowledgeShell>
    );
}
