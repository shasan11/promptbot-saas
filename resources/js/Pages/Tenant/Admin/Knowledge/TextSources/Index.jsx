import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { Link, router, useForm } from '@inertiajs/react';
import { FileText, Plus, Search } from 'lucide-react';

export default function TextSourcesIndex({ documents, filters, bases, languages, can }) {
    const { data, setData, post, transform, processing, errors, reset } = useForm({
        knowledge_base: bases[0]?.uuid || '',
        title: '',
        content: '',
        language: 'en',
        tags: '',
    });

    const submit = (event) => {
        event.preventDefault();
        transform((payload) => ({
            ...payload,
            tags: payload.tags.split(',').map((tag) => tag.trim()).filter(Boolean),
        }));
        post(route('tenant.admin.knowledge.text-sources.store'), {
            preserveScroll: true,
            onSuccess: () => reset('title', 'content', 'tags'),
        });
    };

    return (
        <KnowledgeShell
            title="Text sources"
            description="Write policies, notes and instructions directly in PromptBot and index them like any other source."
        >
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <section className="min-w-0">
                    <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="relative max-w-sm flex-1">
                            <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <Input
                                className="pl-9"
                                placeholder="Search text sources"
                                defaultValue={filters.search || ''}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        router.get(route('tenant.admin.knowledge.text-sources.index'), { ...filters, search: event.currentTarget.value }, { preserveState: true });
                                    }
                                }}
                            />
                        </div>
                        <Select
                            value={filters.knowledge_base || ''}
                            onChange={(event) => router.get(
                                route('tenant.admin.knowledge.text-sources.index'),
                                { ...filters, knowledge_base: event.target.value || undefined },
                                { preserveState: true },
                            )}
                            className="sm:w-64"
                        >
                            <option value="">All knowledge bases</option>
                            {bases.map((base) => <option key={base.uuid} value={base.uuid}>{base.name}</option>)}
                        </Select>
                    </div>

                    {documents.data.length === 0 ? (
                        <EmptyState
                            icon={FileText}
                            title="No text sources yet"
                            description="Create a policy, instruction, or note and it will enter the same processing pipeline as uploaded files."
                        />
                    ) : (
                        <div className="overflow-hidden rounded-md border border-slate-200 bg-white">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Title</th>
                                        <th className="px-4 py-3">Knowledge base</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">Chunks</th>
                                        <th className="px-4 py-3">Updated</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {documents.data.map((document) => (
                                        <tr key={document.uuid}>
                                            <td className="px-4 py-3">
                                                <Link href={route('tenant.admin.knowledge.documents.show', document.uuid)} className="font-semibold text-slate-900 hover:text-brand-700">
                                                    {document.title}
                                                </Link>
                                                <p className="mt-0.5 text-xs text-slate-500">{document.language?.toUpperCase() || 'Auto'} · {document.word_count || 0} words</p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{document.knowledge_base?.name}</td>
                                            <td className="px-4 py-3"><KnowledgeStatusBadge status={document.status} /></td>
                                            <td className="px-4 py-3 text-slate-600">{document.chunk_count?.toLocaleString?.() ?? document.chunk_count ?? 0}</td>
                                            <td className="px-4 py-3 text-slate-500">{document.updated_at ? new Date(document.updated_at).toLocaleString() : '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {can.create && (
                    <aside className="rounded-md border border-slate-200 bg-white p-4">
                        <div className="mb-4 flex items-center gap-2">
                            <Plus className="h-4 w-4 text-brand-600" />
                            <h2 className="text-sm font-semibold text-slate-900">Create text source</h2>
                        </div>
                        <form onSubmit={submit} className="space-y-4">
                            <FormField label="Knowledge base" id="text-knowledge-base" error={errors.knowledge_base}>
                                <Select id="text-knowledge-base" value={data.knowledge_base} onChange={(event) => setData('knowledge_base', event.target.value)} required>
                                    {bases.map((base) => <option key={base.uuid} value={base.uuid}>{base.name}</option>)}
                                </Select>
                            </FormField>
                            <FormField label="Title" id="text-title" error={errors.title}>
                                <Input id="text-title" value={data.title} onChange={(event) => setData('title', event.target.value)} required />
                            </FormField>
                            <FormField label="Content" id="text-content" error={errors.content}>
                                <Textarea
                                    id="text-content"
                                    value={data.content}
                                    onChange={(event) => setData('content', event.target.value)}
                                    rows={12}
                                    required
                                />
                            </FormField>
                            <FormField label="Language" id="text-language" error={errors.language}>
                                <Select id="text-language" value={data.language} onChange={(event) => setData('language', event.target.value)}>
                                    {Object.entries(languages).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </Select>
                            </FormField>
                            <FormField label="Tags" id="text-tags" error={errors.tags} hint="Separate tags with commas.">
                                <Input id="text-tags" value={data.tags} onChange={(event) => setData('tags', event.target.value)} placeholder="policy, support" />
                            </FormField>
                            <button
                                type="submit"
                                disabled={processing || !bases.length}
                                className="inline-flex w-full items-center justify-center rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Saving...' : 'Save and index'}
                            </button>
                        </form>
                    </aside>
                )}
            </div>
        </KnowledgeShell>
    );
}
