import Pagination from '@/Components/Superadmin/Pagination';
import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Bell, Languages, Send, Sparkles, Wand2 } from 'lucide-react';

function CopilotPanel({ conversation, ai, replyForm }) {
    const run = (operation, extra = {}) => router.post(route('tenant.admin.inbox.ai.run', conversation.public_uuid), { operation, ...extra }, { preserveScroll: true });
    const useSuggestion = (suggestion) => {
        replyForm.setData('body', suggestion.text);
        router.post(route('tenant.admin.ai.suggestions.feedback', suggestion.public_uuid), { decision: 'accepted' }, { preserveScroll: true });
    };
    const reject = (suggestion) => router.post(route('tenant.admin.ai.suggestions.feedback', suggestion.public_uuid), { decision: 'rejected' }, { preserveScroll: true });
    const latestDraft = ai?.suggestions?.find((item) => ['draft','rewrite','translate'].includes(item.type) && item.status === 'generated');

    return <section className="rounded-lg border border-brand-100 bg-brand-50/40 p-3">
        <div className="flex items-center gap-2"><Sparkles className="h-4 w-4 text-brand-600" /><h2 className="text-xs font-bold uppercase text-brand-800">AI copilot</h2></div>
        <p className="mt-1 text-xs leading-5 text-slate-500">Suggestions only. Nothing is sent or applied automatically.</p>
        {ai?.insight && <div className="mt-3 space-y-2 rounded-md bg-white p-3 text-xs shadow-soft">
            {ai.insight.summary && <p className="leading-5 text-slate-700">{ai.insight.summary}</p>}
            <div className="flex flex-wrap gap-1">{ai.insight.intent && <Badge>{ai.insight.intent}</Badge>}{ai.insight.sentiment && <Badge>{ai.insight.sentiment}</Badge>}{ai.insight.urgency && <Badge tone={ai.insight.urgency === 'urgent' ? 'danger' : 'neutral'}>{ai.insight.urgency}</Badge>}</div>
        </div>}
        <div className="mt-3 grid grid-cols-2 gap-2"><Button size="sm" variant="secondary" onClick={() => run('summary')}>Summarize</Button><Button size="sm" variant="secondary" onClick={() => run('classify')}>Classify</Button><Button size="sm" icon={Wand2} onClick={() => run('draft')}>Draft reply</Button><Button size="sm" variant="secondary" onClick={() => run('suggest')}>Next steps</Button></div>
        {replyForm.data.body && <div className="mt-2 grid grid-cols-2 gap-2"><Button size="sm" variant="ghost" onClick={() => run('rewrite', { text: replyForm.data.body })}>Rewrite draft</Button><Button size="sm" variant="ghost" icon={Languages} onClick={() => { const language = window.prompt('Translate the draft into which language?'); if (language) run('translate', { text: replyForm.data.body, language }); }}>Translate</Button></div>}
        {latestDraft && <div className="mt-3 rounded-md border border-brand-100 bg-white p-3"><p className="whitespace-pre-wrap text-sm leading-5 text-slate-700">{latestDraft.text}</p>{latestDraft.citations?.length > 0 && <ol className="mt-2 space-y-1 text-[11px] text-slate-500">{latestDraft.citations.map((citation, index) => <li key={index}>[{index + 1}] {citation.document_title || citation.url || 'Workspace source'}</li>)}</ol>}<div className="mt-3 flex gap-2"><Button size="sm" onClick={() => useSuggestion(latestDraft)}>Use draft</Button><Button size="sm" variant="ghost" onClick={() => reject(latestDraft)}>Reject</Button></div></div>}
    </section>;
}

export default function Show({ conversation, messages, users, teams, ai }) {
    const permissions = usePage().props.auth?.permissions || [];
    const form = useForm({ body: '', message_type: 'text' });
    const submit = (event) => { event.preventDefault(); form.post(route('tenant.admin.inbox.reply', conversation.public_uuid), { preserveScroll: true, onSuccess: () => form.reset('body') }); };
    const update = (data) => router.put(route('tenant.admin.inbox.update', conversation.public_uuid), data, { preserveScroll: true });
    const assign = (key, value) => router.post(route('tenant.admin.inbox.assign', conversation.public_uuid), { assignee_id: key === 'assignee_id' ? value : conversation.assignee_id, team_id: key === 'team_id' ? value : conversation.team_id }, { preserveScroll: true });

    return <AuthenticatedLayout title="Inbox"><Head title={conversation.subject || conversation.contact.display_name} />
        <div className="grid min-h-[calc(100vh-7rem)] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft xl:grid-cols-[1fr,320px]">
            <main className="flex min-w-0 flex-col"><header className="flex items-center gap-3 border-b border-slate-200 p-4"><Link href={route('tenant.admin.inbox.index')} className="rounded p-2 hover:bg-slate-100"><ArrowLeft className="h-4 w-4" /></Link><Avatar name={conversation.contact.display_name} size="sm" /><div className="min-w-0 flex-1"><h1 className="truncate font-semibold text-slate-900">{conversation.subject || conversation.contact.display_name}</h1><p className="text-xs text-slate-500">{conversation.channel.name} · {conversation.contact.email || 'No email'}</p></div><Badge tone={conversation.priority === 'urgent' ? 'danger' : conversation.priority === 'high' ? 'warning' : 'neutral'}>{conversation.priority}</Badge></header>
                <div className="flex-1 space-y-4 overflow-y-auto bg-slate-50 p-4">{[...messages.data].reverse().map((message) => <article key={message.public_uuid} className={`flex ${message.direction === 'outbound' ? 'justify-end' : message.direction === 'internal' ? 'justify-center' : 'justify-start'}`}><div className={`max-w-[80%] rounded-xl px-4 py-3 text-sm shadow-soft ${message.direction === 'outbound' ? 'bg-brand-600 text-white' : message.direction === 'internal' ? 'border border-amber-200 bg-amber-50 text-amber-900' : 'bg-white text-slate-800'}`}><p className="whitespace-pre-wrap">{message.body}</p><p className={`mt-2 text-[10px] ${message.direction === 'outbound' ? 'text-brand-100' : 'text-slate-400'}`}>{message.sender_name || 'Customer'} · {new Date(message.sent_at || message.created_at).toLocaleString()} · {message.status}</p>{message.attachments.length > 0 && <div className="mt-2 space-y-1">{message.attachments.map((attachment) => <a key={attachment.public_uuid} href={attachment.download_url} className="block underline">{attachment.original_filename}</a>)}</div>}</div></article>)}</div>
                <Pagination links={messages.links} />
                {permissions.includes('inbox.reply') && <form onSubmit={submit} className="border-t border-slate-200 bg-white p-4"><div className="mb-2 flex gap-2"><button type="button" onClick={() => form.setData('message_type', 'text')} className={`rounded px-3 py-1 text-xs font-medium ${form.data.message_type === 'text' ? 'bg-brand-50 text-brand-700' : 'text-slate-500'}`}>Reply</button><button type="button" onClick={() => form.setData('message_type', 'note')} className={`rounded px-3 py-1 text-xs font-medium ${form.data.message_type === 'note' ? 'bg-amber-50 text-amber-700' : 'text-slate-500'}`}>Internal note</button></div><Textarea value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} placeholder={form.data.message_type === 'note' ? 'Write an internal note. Use @name to mention a teammate.' : 'Write a reply…'} error={!!form.errors.body} /><div className="mt-2 flex justify-end"><Button type="submit" variant="brand" icon={Send} loading={form.processing}>Send</Button></div></form>}
            </main>
            <aside className="space-y-5 border-t border-slate-200 p-4 xl:border-l xl:border-t-0">
                {ai && <CopilotPanel conversation={conversation} ai={ai} replyForm={form} />}
                <section><h2 className="text-xs font-bold uppercase text-slate-400">Customer</h2><Link href={route('tenant.admin.customers.contacts.show', conversation.contact.public_uuid)} className="mt-2 block font-medium text-brand-700">{conversation.contact.display_name}</Link><p className="text-sm text-slate-500">{conversation.contact.email}</p><p className="text-sm text-slate-500">{conversation.contact.company?.name}</p></section>
                <section className="space-y-3"><h2 className="text-xs font-bold uppercase text-slate-400">Conversation</h2><Select value={conversation.status} onChange={(e) => update({ status: e.target.value })}><option value="open">Open</option><option value="pending">Pending</option><option value="waiting_on_customer">Waiting on customer</option><option value="resolved">Resolved</option><option value="closed">Closed</option></Select><Select value={conversation.priority} onChange={(e) => update({ priority: e.target.value })}><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></Select>{permissions.includes('inbox.assign') && <><Select value={conversation.team_id || ''} onChange={(e) => assign('team_id', e.target.value || null)}><option value="">No team</option>{teams.map((team) => <option key={team.id} value={team.id}>{team.name}</option>)}</Select><Select value={conversation.assignee_id || ''} onChange={(e) => assign('assignee_id', e.target.value || null)}><option value="">Unassigned</option>{users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</Select></>}<Button variant="secondary" size="sm" icon={Bell} onClick={() => router.post(route('tenant.admin.inbox.follow', conversation.public_uuid))}>Follow / unfollow</Button></section>
            </aside>
        </div>
    </AuthenticatedLayout>;
}
