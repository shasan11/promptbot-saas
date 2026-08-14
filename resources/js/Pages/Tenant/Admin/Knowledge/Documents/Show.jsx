import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import ProcessingProgress from '@/Components/Knowledge/ProcessingProgress';
import { SectionCard } from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import Tabs from '@/Components/UI/Tabs';
import { Link, router } from '@inertiajs/react';
import { AlertTriangle, Download, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

function formatBytes(bytes) {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

export default function DocumentShow({ document, extractedText, chunks, versions, logs, failures, can }) {
    const [tab, setTab] = useState('preview');
    const [deleteOpen, setDeleteOpen] = useState(false);

    const tabs = [
        { value: 'preview', label: 'Extracted text' },
        { value: 'chunks', label: 'Chunks', count: chunks.length },
        { value: 'metadata', label: 'Metadata' },
        { value: 'versions', label: 'Versions', count: versions.length },
        { value: 'logs', label: 'Processing logs', count: logs.length },
    ];

    return (
        <KnowledgeShell
            title={document.title}
            description={document.original_filename || 'Written directly in PromptBot'}
            actions={(
                <>
                    {can?.download && document.storage_path && (
                        <a
                            href={route('tenant.admin.knowledge.documents.download', document.uuid)}
                            className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <Download className="h-4 w-4" aria-hidden="true" />
                            Download original
                        </a>
                    )}
                    {can?.reindex && (
                        <button
                            type="button"
                            onClick={() => router.post(route('tenant.admin.knowledge.documents.reindex', document.uuid))}
                            className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <RefreshCw className="h-4 w-4" aria-hidden="true" />
                            Re-process
                        </button>
                    )}
                    {can?.delete && (
                        <button
                            type="button"
                            onClick={() => setDeleteOpen(true)}
                            className="inline-flex items-center gap-1.5 rounded-md border border-rose-300 bg-white px-3 py-2 text-sm font-semibold text-rose-700 shadow-sm hover:bg-rose-50"
                        >
                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                            Delete
                        </button>
                    )}
                </>
            )}
        >
            <ConfirmDialog open={deleteOpen} title={`Delete ${document.title}?`} confirmLabel="Delete document" variant="danger" onCancel={() => setDeleteOpen(false)} onConfirm={() => router.delete(route('tenant.admin.knowledge.documents.destroy', document.uuid), { onFinish: () => setDeleteOpen(false) })}>
                This document will stop being used for AI answers immediately. This action cannot be undone.
            </ConfirmDialog>
            {document.last_error && (
                <div className="mb-5 flex gap-3 rounded-lg border border-rose-200 bg-rose-50 p-4" role="alert">
                    <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-rose-600" aria-hidden="true" />
                    <div>
                        <p className="text-sm font-semibold text-rose-900">Processing failed</p>
                        {/* The operator-facing message, not the exception. */}
                        <p className="mt-1 text-sm text-rose-800">{document.last_error}</p>
                    </div>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
                <div className="min-w-0 space-y-4">
                    <Tabs items={tabs} active={tab} onChange={setTab} />

                    {tab === 'preview' && (
                        <SectionCard title="Extracted text" description="This is exactly what your AI agents read — not the original formatting.">
                            {extractedText ? (
                                <pre className="max-h-[32rem] overflow-auto whitespace-pre-wrap rounded-md bg-slate-50 p-4 text-sm leading-relaxed text-slate-700">
                                    {extractedText}
                                </pre>
                            ) : (
                                <p className="text-sm text-slate-500">No text has been extracted yet.</p>
                            )}
                        </SectionCard>
                    )}

                    {tab === 'chunks' && (
                        <SectionCard title="Chunks" description="The retrieval units this document was split into.">
                            {chunks.length ? (
                                <ol className="space-y-3">
                                    {chunks.map((chunk) => (
                                        <li key={chunk.uuid} className="rounded-md border border-slate-200 p-3">
                                            <div className="flex items-center justify-between gap-3 text-xs text-slate-500">
                                                <span className="font-semibold text-slate-700">Chunk {chunk.chunk_index + 1}</span>
                                                <span className="flex items-center gap-2">
                                                    {chunk.token_count} tokens
                                                    <KnowledgeStatusBadge status={chunk.embedding_status === 'ready' ? 'ready' : chunk.embedding_status} />
                                                </span>
                                            </div>
                                            <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">{chunk.content}</p>
                                            {chunk.metadata?.heading && (
                                                <p className="mt-2 text-xs text-slate-400">Section: {chunk.metadata.heading}</p>
                                            )}
                                            {chunk.metadata?.page && (
                                                <p className="mt-1 text-xs text-slate-400">Page {chunk.metadata.page}</p>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            ) : (
                                <p className="text-sm text-slate-500">No chunks yet — this document has not finished processing.</p>
                            )}
                        </SectionCard>
                    )}

                    {tab === 'metadata' && (
                        <SectionCard title="Metadata">
                            <dl className="grid gap-4 sm:grid-cols-2">
                                {[
                                    ['File type', document.mime_type || '—'],
                                    ['Size', formatBytes(document.file_size)],
                                    ['Language', document.language || 'Not detected'],
                                    ['Characters', document.character_count?.toLocaleString() || '0'],
                                    ['Words', document.word_count?.toLocaleString() || '0'],
                                    ['Pages', document.page_count || '—'],
                                    ['Chunks', document.chunk_count],
                                    ['OCR applied', document.ocr_applied ? 'Yes' : 'No'],
                                    ['Contains tables', document.has_tables ? 'Yes' : 'No'],
                                    ['Version', document.version_number],
                                    ['Uploaded by', document.uploader?.name || '—'],
                                    ['Last indexed', document.indexed_at ? new Date(document.indexed_at).toLocaleString() : 'Never'],
                                ].map(([label, value]) => (
                                    <div key={label}>
                                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</dt>
                                        <dd className="mt-0.5 text-sm text-slate-800">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </SectionCard>
                    )}

                    {tab === 'versions' && (
                        <SectionCard title="Version history">
                            {versions.length ? (
                                <ul className="divide-y divide-slate-100">
                                    {versions.map((version) => (
                                        <li key={version.uuid} className="flex items-center justify-between gap-3 py-3 first:pt-0">
                                            <div>
                                                <p className="text-sm font-medium text-slate-800">Version {version.version_number}</p>
                                                <p className="mt-0.5 text-xs text-slate-500">
                                                    {version.change_summary || 'No summary'} · {new Date(version.created_at).toLocaleString()}
                                                    {version.author?.name ? ` · ${version.author.name}` : ''}
                                                </p>
                                            </div>
                                            {version.is_active && <KnowledgeStatusBadge status="active" label="Active" />}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-slate-500">Only the original version exists.</p>
                            )}
                        </SectionCard>
                    )}

                    {tab === 'logs' && (
                        <SectionCard title="Processing logs">
                            {logs.length ? (
                                <ul className="space-y-2">
                                    {logs.map((log) => (
                                        <li key={log.id} className="flex items-baseline gap-3 text-sm">
                                            <span className={`shrink-0 text-xs font-semibold uppercase ${
                                                log.level === 'error' ? 'text-rose-600' : log.level === 'warning' ? 'text-amber-600' : 'text-slate-400'
                                            }`}>
                                                {log.stage}
                                            </span>
                                            <span className="flex-1 text-slate-700">{log.message}</span>
                                            <span className="shrink-0 text-xs text-slate-400">
                                                {log.duration_ms ? `${log.duration_ms}ms` : ''}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-slate-500">No processing activity recorded yet.</p>
                            )}

                            {failures.length > 0 && can?.viewTechnicalDetails && (
                                <details className="mt-5 border-t border-slate-100 pt-4">
                                    <summary className="cursor-pointer text-xs font-semibold text-slate-600">
                                        Technical failure details (administrators only)
                                    </summary>
                                    {failures.map((failure) => (
                                        <pre key={failure.uuid} className="mt-2 max-h-64 overflow-auto rounded-md bg-slate-900 p-3 text-[11px] text-slate-100">
                                            {failure.technical_details || failure.message}
                                        </pre>
                                    ))}
                                </details>
                            )}
                        </SectionCard>
                    )}
                </div>

                <div className="space-y-4">
                    <SectionCard title="Status">
                        <div className="mb-4"><KnowledgeStatusBadge status={document.status} /></div>
                        <ProcessingProgress
                            stage={document.current_stage}
                            status={document.status}
                            failureStage={document.failure_stage}
                        />
                    </SectionCard>

                    <SectionCard title="Where this lives">
                        <dl className="space-y-3 text-sm">
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">Knowledge base</dt>
                                <dd className="mt-0.5">
                                    <Link href={route('tenant.admin.knowledge.bases.show', document.knowledge_base.uuid)} className="text-brand-700 hover:underline">
                                        {document.knowledge_base.name}
                                    </Link>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">Source</dt>
                                <dd className="mt-0.5">
                                    <Link href={route('tenant.admin.knowledge.sources.show', document.source.uuid)} className="text-brand-700 hover:underline">
                                        {document.source.name}
                                    </Link>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">Collection</dt>
                                <dd className="mt-0.5 text-slate-700">{document.collection?.name || 'None'}</dd>
                            </div>
                        </dl>
                    </SectionCard>
                </div>
            </div>
        </KnowledgeShell>
    );
}
