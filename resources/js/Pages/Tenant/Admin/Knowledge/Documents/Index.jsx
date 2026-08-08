import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import ProcessingProgress from '@/Components/Knowledge/ProcessingProgress';
import UploadZone from '@/Components/Knowledge/UploadZone';
import Pagination from '@/Components/Superadmin/Pagination';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import { FilterBar } from '@/Components/UI/FilterBar';
import FormField from '@/Components/UI/FormField';
import Modal from '@/Components/UI/Modal';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import { Link, router, useForm } from '@inertiajs/react';
import { FileText, Upload } from 'lucide-react';
import { useState } from 'react';

function formatBytes(bytes) {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

export default function DocumentsIndex({ documents, filters, bases, statuses, languages, can }) {
    const [search, setSearch] = useState(filters.search || '');
    const [uploadOpen, setUploadOpen] = useState(false);
    const [files, setFiles] = useState([]);

    const { data, setData, post, processing, progress, errors, reset } = useForm({
        knowledge_base: bases[0]?.uuid || '',
        files: [],
        on_duplicate: 'skip',
    });

    const applyFilter = (changes) => {
        router.get(route('tenant.admin.knowledge.documents.index'), { ...filters, ...changes }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    };

    const submitUpload = (event) => {
        event.preventDefault();

        post(route('tenant.admin.knowledge.documents.store'), {
            forceFormData: true,
            data: { ...data, files },
            onSuccess: () => { setUploadOpen(false); setFiles([]); reset('files'); },
        });
    };

    return (
        <KnowledgeShell
            title="Documents"
            description="Every file and article your AI agents can draw on, with its processing state."
            actions={can?.upload && bases.length > 0 && (
                <button
                    type="button"
                    onClick={() => setUploadOpen(true)}
                    className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800"
                >
                    <Upload className="h-4 w-4" aria-hidden="true" />
                    Upload documents
                </button>
            )}
        >
            <FilterBar className="mb-4">
                <form onSubmit={(event) => { event.preventDefault(); applyFilter({ search }); }} className="min-w-[16rem] flex-1">
                    <SearchInput
                        value={search}
                        onChange={setSearch}
                        onClear={() => { setSearch(''); applyFilter({ search: '' }); }}
                        placeholder="Search by filename, title or tag"
                    />
                </form>

                <div className="w-48">
                    <label htmlFor="doc-base" className="sr-only">Filter by knowledge base</label>
                    <Select id="doc-base" value={filters.knowledge_base || ''} onChange={(event) => applyFilter({ knowledge_base: event.target.value })}>
                        <option value="">All knowledge bases</option>
                        {bases.map((base) => <option key={base.uuid} value={base.uuid}>{base.name}</option>)}
                    </Select>
                </div>

                <div className="w-44">
                    <label htmlFor="doc-status" className="sr-only">Filter by status</label>
                    <Select id="doc-status" value={filters.status || ''} onChange={(event) => applyFilter({ status: event.target.value })}>
                        <option value="">All statuses</option>
                        {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                    </Select>
                </div>
            </FilterBar>

            {documents.data.length === 0 ? (
                <EmptyState
                    icon={FileText}
                    title={Object.values(filters).some(Boolean) ? 'No documents match those filters' : 'No documents have been added'}
                    description={Object.values(filters).some(Boolean)
                        ? 'Clear the filters to see everything in your knowledge bases.'
                        : 'Upload PDFs, Word files, spreadsheets or presentations and your agents will be able to answer from them.'}
                    action={can?.upload && bases.length > 0 && (
                        <button
                            type="button"
                            onClick={() => setUploadOpen(true)}
                            className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800"
                        >
                            <Upload className="h-4 w-4" aria-hidden="true" />
                            Upload documents
                        </button>
                    )}
                />
            ) : (
                <>
                    <div className="hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:block">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    {['Document', 'Knowledge base', 'Type', 'Size', 'Chunks', 'Status', 'Progress'].map((heading) => (
                                        <th key={heading} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">{heading}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {documents.data.map((document) => (
                                    <tr key={document.uuid} className="hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <Link href={route('tenant.admin.knowledge.documents.show', document.uuid)} className="font-medium text-slate-900 hover:text-brand-700">
                                                {document.title}
                                            </Link>
                                            {document.original_filename && <p className="mt-0.5 truncate text-xs text-slate-400">{document.original_filename}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{document.knowledge_base?.name}</td>
                                        <td className="px-4 py-3 uppercase text-xs text-slate-500">{document.extension || document.kind}</td>
                                        <td className="px-4 py-3 text-slate-600">{formatBytes(document.file_size)}</td>
                                        <td className="px-4 py-3 text-slate-600">{document.chunk_count}</td>
                                        <td className="px-4 py-3"><KnowledgeStatusBadge status={document.status} /></td>
                                        <td className="px-4 py-3">
                                            <ProcessingProgress
                                                compact
                                                stage={document.current_stage}
                                                status={document.status}
                                                failureStage={document.failure_stage}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <ul className="space-y-3 lg:hidden">
                        {documents.data.map((document) => (
                            <li key={document.uuid} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                <div className="flex items-start justify-between gap-3">
                                    <Link href={route('tenant.admin.knowledge.documents.show', document.uuid)} className="min-w-0 flex-1 truncate font-medium text-slate-900">
                                        {document.title}
                                    </Link>
                                    <KnowledgeStatusBadge status={document.status} />
                                </div>
                                <p className="mt-1 text-xs text-slate-500">
                                    {document.knowledge_base?.name} · {formatBytes(document.file_size)} · {document.chunk_count} chunks
                                </p>
                                {document.last_error && <p className="mt-2 text-xs text-rose-600">{document.last_error}</p>}
                            </li>
                        ))}
                    </ul>

                    <Pagination links={documents.links} />
                </>
            )}

            <Modal show={uploadOpen} onClose={() => setUploadOpen(false)} maxWidth="2xl">
                <form onSubmit={submitUpload} className="p-6">
                    <h2 className="text-lg font-bold text-slate-900">Upload documents</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Files are processed in the background. Your agents can use them as soon as processing finishes.
                    </p>

                    <div className="mt-5 space-y-4">
                        <FormField label="Knowledge base" required id="upload-base" error={errors.knowledge_base}>
                            <Select id="upload-base" value={data.knowledge_base} onChange={(event) => setData('knowledge_base', event.target.value)}>
                                {bases.map((base) => <option key={base.uuid} value={base.uuid}>{base.name}</option>)}
                            </Select>
                        </FormField>

                        <UploadZone files={files} onChange={setFiles} uploading={processing} progress={progress?.percentage || 0} />

                        <FormField label="If a file is already in this knowledge base" id="upload-duplicate">
                            <Select id="upload-duplicate" value={data.on_duplicate} onChange={(event) => setData('on_duplicate', event.target.value)}>
                                <option value="skip">Skip it (recommended — avoids re-processing costs)</option>
                                <option value="replace">Replace the existing copy</option>
                                <option value="add">Add it anyway as a separate document</option>
                            </Select>
                        </FormField>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setUploadOpen(false)} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing || files.length === 0}
                            className="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing ? 'Uploading…' : `Upload ${files.length || ''} file${files.length === 1 ? '' : 's'}`}
                        </button>
                    </div>
                </form>
            </Modal>
        </KnowledgeShell>
    );
}
