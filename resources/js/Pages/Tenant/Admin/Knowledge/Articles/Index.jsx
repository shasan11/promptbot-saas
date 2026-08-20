import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import Pagination from '@/Components/Superadmin/Pagination';
import EmptyState from '@/Components/UI/EmptyState';
import Button from '@/Components/UI/Button';
import { FilterBar } from '@/Components/UI/FilterBar';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { router, useForm } from '@inertiajs/react';
import { FileEdit, Plus } from 'lucide-react';
import { useState } from 'react';

const emptyForm = { knowledge_base: '', title: '', summary: '', body: '', tags: '' };

export default function ArticlesIndex({ articles, filters, bases, statuses, languages, can }) {
    const [search, setSearch] = useState(filters.search || '');
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [versionsFor, setVersionsFor] = useState(null);
    const [versions, setVersions] = useState([]);
    const [rejecting, setRejecting] = useState(null);
    const [reviewNote, setReviewNote] = useState('');

    const { data, setData, post, put, transform, processing, errors, reset } = useForm({
        knowledge_base: bases[0]?.uuid || '',
        title: '',
        summary: '',
        body: '',
        tags: '',
    });

    const applyFilter = (changes) => router.get(route('tenant.admin.knowledge.articles.index'), { ...filters, ...changes }, {
        preserveState: true, preserveScroll: true, replace: true,
    });

    const openCreate = () => {
        setEditing(null);
        setData({ ...emptyForm, knowledge_base: bases[0]?.uuid || '' });
        setOpen(true);
    };

    const openEdit = (article) => {
        setEditing(article);
        setData({ knowledge_base: article.knowledge_base?.uuid || '', title: article.title, summary: article.summary || '', body: article.body || '', tags: '' });
        setOpen(true);
    };

    const submit = (e) => {
        e.preventDefault();

        transform((current) => ({
            ...current,
            tags: current.tags ? current.tags.split(',').map((t) => t.trim()).filter(Boolean) : [],
        }));

        const onSuccess = () => { setOpen(false); reset(); };

        if (editing) {
            put(route('tenant.admin.knowledge.articles.update', editing.uuid), { onSuccess });
        } else {
            post(route('tenant.admin.knowledge.articles.store'), { onSuccess });
        }
    };

    const submitForReview = (article) => router.post(route('tenant.admin.knowledge.articles.submit-for-review', article.uuid), {}, { preserveScroll: true });
    const archive = (article) => router.post(route('tenant.admin.knowledge.articles.archive', article.uuid), {}, { preserveScroll: true });
    const restore = (article) => router.post(route('tenant.admin.knowledge.articles.restore', article.uuid), {}, { preserveScroll: true });

    const approve = (article) => router.post(route('tenant.admin.knowledge.articles.approve', article.uuid), {}, { preserveScroll: true });
    const reject = (e) => {
        e.preventDefault();
        router.post(route('tenant.admin.knowledge.articles.reject', rejecting.uuid), { review_note: reviewNote }, {
            preserveScroll: true, onSuccess: () => { setRejecting(null); setReviewNote(''); },
        });
    };

    const showVersions = async (article) => {
        setVersionsFor(article);
        const res = await fetch(route('tenant.admin.knowledge.articles.versions', article.uuid));
        const payload = await res.json();
        setVersions(payload.versions || []);
    };

    return (
        <KnowledgeShell
            title="Articles"
            description="Longer, authored knowledge — policies, procedures, how-tos. Articles go through review before your team or AI agents can use them."
            actions={can?.create && bases.length > 0 && (
                <Button type="button" variant="brand" size="md" icon={Plus} onClick={openCreate}>Add article</Button>
            )}
        >
            <FilterBar className="mb-4">
                <form onSubmit={(e) => { e.preventDefault(); applyFilter({ search }); }} className="min-w-[16rem] flex-1">
                    <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); applyFilter({ search: '' }); }} placeholder="Search articles" />
                </form>
                <div className="w-44">
                    <label htmlFor="article-status" className="sr-only">Filter by status</label>
                    <Select id="article-status" value={filters.status || ''} onChange={(e) => applyFilter({ status: e.target.value })}>
                        <option value="">All statuses</option>
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </Select>
                </div>
            </FilterBar>

            {articles.data.length === 0 ? (
                <EmptyState
                    icon={FileEdit}
                    title={Object.values(filters).some(Boolean) ? 'No articles match those filters' : 'No articles yet'}
                    description="Write a policy or how-to, send it for review, and it will be available once approved."
                    action={can?.create && bases.length > 0 && (
                        <Button type="button" variant="brand" icon={Plus} onClick={openCreate}>Add article</Button>
                    )}
                />
            ) : (
                <>
                    <ul className="space-y-3">
                        {articles.data.map((article) => (
                            <li key={article.uuid} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-slate-900">{article.title}</p>
                                        {article.summary && <p className="mt-1.5 line-clamp-2 text-sm text-slate-600">{article.summary}</p>}
                                        <p className="mt-2 text-xs text-slate-400">
                                            {article.knowledge_base?.name}
                                            {article.author ? ` · by ${article.author.name}` : ''}
                                            {article.status === 'draft' && article.review_note ? ` · Reviewer note: ${article.review_note}` : ''}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                                        <KnowledgeStatusBadge status={article.status} />
                                        <button type="button" onClick={() => showVersions(article)} className="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Versions</button>
                                        {article.status === 'draft' && (
                                            <>
                                                <button type="button" onClick={() => openEdit(article)} className="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</button>
                                                <button type="button" onClick={() => submitForReview(article)} className="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Submit for review</button>
                                            </>
                                        )}
                                        {article.status === 'in_review' && can?.approve && (
                                            <>
                                                <button type="button" onClick={() => approve(article)} className="rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Approve &amp; publish</button>
                                                <button type="button" onClick={() => setRejecting(article)} className="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Request changes</button>
                                            </>
                                        )}
                                        {article.status === 'published' && can?.approve && (
                                            <button type="button" onClick={() => archive(article)} className="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Archive</button>
                                        )}
                                        {article.status === 'archived' && can?.approve && (
                                            <button type="button" onClick={() => restore(article)} className="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Restore</button>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <Pagination links={articles.links} />
                </>
            )}

            <Modal show={open} onClose={() => setOpen(false)} maxWidth="3xl">
                <form onSubmit={submit} className="p-6">
                    <h2 className="text-lg font-bold text-slate-900">{editing ? 'Edit article' : 'Add an article'}</h2>
                    <p className="mt-1 text-sm text-slate-500">Saved as a draft. Submit it for review when it is ready — it will not answer questions until someone approves it.</p>

                    <div className="mt-5 space-y-4">
                        {!editing && (
                            <FormField label="Knowledge base" required id="a-base" error={errors.knowledge_base}>
                                <Select id="a-base" value={data.knowledge_base} onChange={(e) => setData('knowledge_base', e.target.value)}>
                                    {bases.map((b) => <option key={b.uuid} value={b.uuid}>{b.name}</option>)}
                                </Select>
                            </FormField>
                        )}
                        <FormField label="Title" required id="a-title" error={errors.title}>
                            <Input id="a-title" value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="Refund policy" />
                        </FormField>
                        <FormField label="Summary" id="a-summary" error={errors.summary} hint="Shown to reviewers and used as a search hint.">
                            <Input id="a-summary" value={data.summary} onChange={(e) => setData('summary', e.target.value)} />
                        </FormField>
                        <FormField label="Body" required id="a-body" error={errors.body}>
                            <Textarea id="a-body" rows={10} value={data.body} onChange={(e) => setData('body', e.target.value)} placeholder="Write the full article here…" />
                        </FormField>
                        <FormField label="Tags" id="a-tags" hint="Comma-separated">
                            <Input id="a-tags" value={data.tags} onChange={(e) => setData('tags', e.target.value)} placeholder="billing, refunds" />
                        </FormField>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setOpen(false)} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" disabled={processing} className="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:opacity-50">
                            {processing ? 'Saving…' : 'Save article'}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal show={!!rejecting} onClose={() => setRejecting(null)} maxWidth="lg">
                <form onSubmit={reject} className="p-6">
                    <h2 className="text-lg font-bold text-slate-900">Request changes</h2>
                    <p className="mt-1 text-sm text-slate-500">Sends "{rejecting?.title}" back to its author with your notes.</p>
                    <div className="mt-4">
                        <FormField label="What needs to change?" required id="a-note">
                            <Textarea id="a-note" rows={4} value={reviewNote} onChange={(e) => setReviewNote(e.target.value)} />
                        </FormField>
                    </div>
                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setRejecting(null)} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" className="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">Send back</button>
                    </div>
                </form>
            </Modal>

            <Modal show={!!versionsFor} onClose={() => setVersionsFor(null)} maxWidth="xl">
                <div className="p-6">
                    <h2 className="text-lg font-bold text-slate-900">Version history — {versionsFor?.title}</h2>
                    {versions.length === 0 ? (
                        <p className="mt-3 text-sm text-slate-500">No previous versions yet.</p>
                    ) : (
                        <ul className="mt-4 space-y-3">
                            {versions.map((v) => (
                                <li key={v.version_number} className="rounded-md border border-slate-200 p-3">
                                    <p className="text-sm font-semibold text-slate-800">Version {v.version_number} — {v.title}</p>
                                    <p className="text-xs text-slate-400">{v.change_summary}{v.author ? ` · ${v.author}` : ''}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </Modal>
        </KnowledgeShell>
    );
}
