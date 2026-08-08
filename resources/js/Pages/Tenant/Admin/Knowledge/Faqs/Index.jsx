import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import Pagination from '@/Components/Superadmin/Pagination';
import EmptyState from '@/Components/UI/EmptyState';
import { FilterBar } from '@/Components/UI/FilterBar';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { router, useForm } from '@inertiajs/react';
import { HelpCircle, Plus } from 'lucide-react';
import { useState } from 'react';

export default function FaqsIndex({ faqs, filters, bases, categories, statuses, languages, can }) {
    const [search, setSearch] = useState(filters.search || '');
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        knowledge_base: bases[0]?.uuid || '',
        question: '',
        answer: '',
        category: '',
        status: 'draft',
    });

    const applyFilter = (changes) => router.get(route('tenant.admin.knowledge.faqs.index'), { ...filters, ...changes }, {
        preserveState: true, preserveScroll: true, replace: true,
    });

    return (
        <KnowledgeShell
            title="FAQs"
            description="Short, structured answers to the questions customers actually ask. These are the highest-quality knowledge you can give an agent."
            actions={can?.create && bases.length > 0 && (
                <button type="button" onClick={() => setOpen(true)} className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    Add FAQ
                </button>
            )}
        >
            <FilterBar className="mb-4">
                <form onSubmit={(e) => { e.preventDefault(); applyFilter({ search }); }} className="min-w-[16rem] flex-1">
                    <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); applyFilter({ search: '' }); }} placeholder="Search questions and answers" />
                </form>
                <div className="w-44">
                    <label htmlFor="faq-status" className="sr-only">Filter by status</label>
                    <Select id="faq-status" value={filters.status || ''} onChange={(e) => applyFilter({ status: e.target.value })}>
                        <option value="">All statuses</option>
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </Select>
                </div>
                {categories.length > 0 && (
                    <div className="w-44">
                        <label htmlFor="faq-category" className="sr-only">Filter by category</label>
                        <Select id="faq-category" value={filters.category || ''} onChange={(e) => applyFilter({ category: e.target.value })}>
                            <option value="">All categories</option>
                            {categories.map((c) => <option key={c} value={c}>{c}</option>)}
                        </Select>
                    </div>
                )}
            </FilterBar>

            {faqs.data.length === 0 ? (
                <EmptyState
                    icon={HelpCircle}
                    title={Object.values(filters).some(Boolean) ? 'No FAQs match those filters' : 'No FAQs yet'}
                    description="Write a question and its answer, publish it, and your agents will use it straight away."
                    action={can?.create && bases.length > 0 && (
                        <button type="button" onClick={() => setOpen(true)} className="rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">Add FAQ</button>
                    )}
                />
            ) : (
                <>
                    <ul className="space-y-3">
                        {faqs.data.map((faq) => (
                            <li key={faq.uuid} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-slate-900">{faq.question}</p>
                                        <p className="mt-1.5 line-clamp-3 text-sm text-slate-600">{faq.answer}</p>
                                        <p className="mt-2 text-xs text-slate-400">
                                            {faq.knowledge_base?.name}
                                            {faq.category ? ` · ${faq.category}` : ''}
                                            {` · ${languages[faq.language] || faq.language}`}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <KnowledgeStatusBadge status={faq.status} />
                                        <button
                                            type="button"
                                            onClick={() => router.post(
                                                route('tenant.admin.knowledge.faqs.publish', faq.uuid),
                                                { published: faq.status !== 'published' },
                                                { preserveScroll: true }
                                            )}
                                            className="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            {faq.status === 'published' ? 'Unpublish' : 'Publish'}
                                        </button>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <Pagination links={faqs.links} />
                </>
            )}

            <Modal show={open} onClose={() => setOpen(false)} maxWidth="2xl">
                <form onSubmit={(e) => { e.preventDefault(); post(route('tenant.admin.knowledge.faqs.store'), { onSuccess: () => { setOpen(false); reset(); } }); }} className="p-6">
                    <h2 className="text-lg font-bold text-slate-900">Add an FAQ</h2>
                    <p className="mt-1 text-sm text-slate-500">Question and answer stay together, so agents retrieve them as one piece of knowledge.</p>

                    <div className="mt-5 space-y-4">
                        <FormField label="Knowledge base" required id="f-base" error={errors.knowledge_base}>
                            <Select id="f-base" value={data.knowledge_base} onChange={(e) => setData('knowledge_base', e.target.value)}>
                                {bases.map((b) => <option key={b.uuid} value={b.uuid}>{b.name}</option>)}
                            </Select>
                        </FormField>
                        <FormField label="Question" required id="f-q" error={errors.question}>
                            <Input id="f-q" value={data.question} onChange={(e) => setData('question', e.target.value)} placeholder="How long is our refund period?" />
                        </FormField>
                        <FormField label="Answer" required id="f-a" error={errors.answer}>
                            <Textarea id="f-a" rows={5} value={data.answer} onChange={(e) => setData('answer', e.target.value)} placeholder="Customers may request a refund within 30 days of purchase…" />
                        </FormField>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Category" id="f-cat" error={errors.category} hint="Billing, Shipping, Account…">
                                <Input id="f-cat" value={data.category} onChange={(e) => setData('category', e.target.value)} />
                            </FormField>
                            <FormField label="Status" id="f-status">
                                <Select id="f-status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                    <option value="draft">Draft — not used by agents yet</option>
                                    <option value="published">Published — agents can use it immediately</option>
                                </Select>
                            </FormField>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setOpen(false)} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" disabled={processing} className="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:opacity-50">
                            {processing ? 'Saving…' : 'Save FAQ'}
                        </button>
                    </div>
                </form>
            </Modal>
        </KnowledgeShell>
    );
}
