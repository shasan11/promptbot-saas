import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import Pagination from '@/Components/Superadmin/Pagination';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import Modal from '@/Components/UI/Modal';
import Textarea from '@/Components/UI/Textarea';
import { router } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { useState } from 'react';

export default function ReviewQueue({ articles, can }) {
    const [previewing, setPreviewing] = useState(null);
    const [rejecting, setRejecting] = useState(null);
    const [reviewNote, setReviewNote] = useState('');

    const approve = (article) => router.post(route('tenant.admin.knowledge.articles.approve', article.uuid), {}, { preserveScroll: true });

    const reject = (e) => {
        e.preventDefault();
        router.post(route('tenant.admin.knowledge.articles.reject', rejecting.uuid), { review_note: reviewNote }, {
            preserveScroll: true, onSuccess: () => { setRejecting(null); setReviewNote(''); },
        });
    };

    return (
        <KnowledgeShell
            title="Review queue"
            description="Review knowledge before it becomes available to your team and AI agents."
        >
            {articles.data.length === 0 ? (
                <EmptyState
                    icon={ClipboardCheck}
                    title="Nothing waiting for review"
                    description="Articles submitted for review by their authors will show up here."
                />
            ) : (
                <>
                    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Article</th>
                                    <th className="px-4 py-3">Author</th>
                                    <th className="px-4 py-3">Knowledge base</th>
                                    <th className="px-4 py-3">Submitted</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {articles.data.map((article) => (
                                    <tr key={article.uuid}>
                                        <td className="px-4 py-3 font-medium text-slate-900">{article.title}</td>
                                        <td className="px-4 py-3 text-slate-600">{article.author?.name || '—'}</td>
                                        <td className="px-4 py-3 text-slate-600">{article.knowledge_base?.name}</td>
                                        <td className="px-4 py-3 text-slate-500">{article.review_requested_at ? new Date(article.review_requested_at).toLocaleDateString() : '—'}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <button type="button" onClick={() => setPreviewing(article)} className="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Preview</button>
                                                {can?.approve && (
                                                    <>
                                                        <button type="button" onClick={() => approve(article)} className="rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Approve &amp; publish</button>
                                                        <button type="button" onClick={() => setRejecting(article)} className="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Request changes</button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={articles.links} />
                </>
            )}

            <Modal show={!!previewing} onClose={() => setPreviewing(null)} maxWidth="2xl">
                <div className="p-6">
                    <h2 className="text-lg font-bold text-slate-900">{previewing?.title}</h2>
                    {previewing?.summary && <p className="mt-1 text-sm text-slate-500">{previewing.summary}</p>}
                    <div className="mt-4 max-h-96 overflow-y-auto whitespace-pre-wrap text-sm text-slate-700">{previewing?.body}</div>
                </div>
            </Modal>

            <Modal show={!!rejecting} onClose={() => setRejecting(null)} maxWidth="lg">
                <form onSubmit={reject} className="p-6">
                    <h2 className="text-lg font-bold text-slate-900">Request changes</h2>
                    <p className="mt-1 text-sm text-slate-500">Sends "{rejecting?.title}" back to its author with your notes.</p>
                    <div className="mt-4">
                        <FormField label="What needs to change?" required id="rq-note">
                            <Textarea id="rq-note" rows={4} value={reviewNote} onChange={(e) => setReviewNote(e.target.value)} />
                        </FormField>
                    </div>
                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setRejecting(null)} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" className="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">Send back</button>
                    </div>
                </form>
            </Modal>
        </KnowledgeShell>
    );
}
