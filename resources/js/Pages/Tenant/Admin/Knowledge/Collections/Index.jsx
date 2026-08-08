import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import { FilterBar } from '@/Components/UI/FilterBar';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { router, useForm } from '@inertiajs/react';
import { Folder, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function CollectionsIndex({ collections, bases, filters, maxDepth, can }) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        knowledge_base: filters.knowledge_base || bases[0]?.uuid || '',
        name: '',
        description: '',
        parent_id: '',
    });

    const baseId = bases.find((b) => b.uuid === data.knowledge_base)?.id;
    const parentOptions = collections.filter((c) => c.knowledge_base_id === baseId && c.depth < maxDepth - 1);

    return (
        <KnowledgeShell
            title="Collections"
            description={`Group related documents inside a knowledge base. Access granted on a collection cascades to everything nested beneath it. Up to ${maxDepth} levels deep.`}
            actions={can?.create && bases.length > 0 && (
                <button type="button" onClick={() => setOpen(true)} className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    New collection
                </button>
            )}
        >
            <FilterBar className="mb-4">
                <div className="w-56">
                    <label htmlFor="col-base" className="sr-only">Filter by knowledge base</label>
                    <Select
                        id="col-base"
                        value={filters.knowledge_base || ''}
                        onChange={(e) => router.get(route('tenant.admin.knowledge.collections.index'), { knowledge_base: e.target.value }, { preserveState: true, replace: true })}
                    >
                        <option value="">All knowledge bases</option>
                        {bases.map((b) => <option key={b.uuid} value={b.uuid}>{b.name}</option>)}
                    </Select>
                </div>
            </FilterBar>

            {collections.length === 0 ? (
                <EmptyState
                    icon={Folder}
                    title="No collections yet"
                    description="Collections are optional. Create them when a knowledge base grows large enough that agents should only see part of it."
                    action={can?.create && bases.length > 0 && (
                        <button type="button" onClick={() => setOpen(true)} className="rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">New collection</button>
                    )}
                />
            ) : (
                <SectionCard title="Collections" padded={false}>
                    <ul className="divide-y divide-slate-100">
                        {collections.map((collection) => (
                            <li key={collection.uuid} className="flex items-center justify-between gap-3 px-5 py-3">
                                <div className="min-w-0" style={{ paddingLeft: `${collection.depth * 1.25}rem` }}>
                                    <p className="text-sm font-medium text-slate-800">{collection.name}</p>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {collection.knowledge_base?.name}
                                        {collection.parent ? ` · under ${collection.parent.name}` : ''}
                                        {` · ${collection.documents_count} document(s)`}
                                    </p>
                                </div>
                                {can?.create && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (window.confirm(`Delete "${collection.name}"? Its documents move to the knowledge base root and stay searchable.`)) {
                                                router.delete(route('tenant.admin.knowledge.collections.destroy', collection.uuid), { preserveScroll: true });
                                            }
                                        }}
                                        aria-label={`Delete ${collection.name}`}
                                        className="shrink-0 rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                </SectionCard>
            )}

            <Modal show={open} onClose={() => setOpen(false)}>
                <form onSubmit={(e) => { e.preventDefault(); post(route('tenant.admin.knowledge.collections.store'), { onSuccess: () => { setOpen(false); reset(); } }); }} className="p-6">
                    <h2 className="text-lg font-bold text-slate-900">New collection</h2>

                    <div className="mt-5 space-y-4">
                        <FormField label="Knowledge base" required id="c-base" error={errors.knowledge_base}>
                            <Select id="c-base" value={data.knowledge_base} onChange={(e) => { setData('knowledge_base', e.target.value); setData('parent_id', ''); }}>
                                {bases.map((b) => <option key={b.uuid} value={b.uuid}>{b.name}</option>)}
                            </Select>
                        </FormField>
                        <FormField label="Name" required id="c-name" error={errors.name}>
                            <Input id="c-name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Product documentation" />
                        </FormField>
                        <FormField label="Description" id="c-desc" error={errors.description}>
                            <Textarea id="c-desc" rows={2} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        </FormField>
                        {parentOptions.length > 0 && (
                            <FormField label="Nest inside" id="c-parent" error={errors.parent_id}>
                                <Select id="c-parent" value={data.parent_id} onChange={(e) => setData('parent_id', e.target.value)}>
                                    <option value="">Top level</option>
                                    {parentOptions.map((c) => <option key={c.id} value={c.id}>{'— '.repeat(c.depth)}{c.name}</option>)}
                                </Select>
                            </FormField>
                        )}
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setOpen(false)} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" disabled={processing} className="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:opacity-50">
                            {processing ? 'Creating…' : 'Create collection'}
                        </button>
                    </div>
                </form>
            </Modal>
        </KnowledgeShell>
    );
}
