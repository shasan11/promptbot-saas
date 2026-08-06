import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950';

function Detail({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</dt>
            <dd className="mt-1 text-sm font-semibold text-slate-800">{children || '-'}</dd>
        </div>
    );
}

export default function Show({ ticket, administrators = [] }) {
    const { auth } = usePage().props;
    const canManage = auth?.permissions?.includes('support.manage');
    const updateForm = useForm({
        subject: ticket.subject || '',
        status: ticket.status || 'open',
        priority: ticket.priority || 'normal',
        category: ticket.category || '',
        assigned_to: ticket.assigned_to || '',
        requester_name: ticket.requester_name || '',
        requester_email: ticket.requester_email || '',
        sla_due_at: ticket.sla_due_at ? ticket.sla_due_at.slice(0, 16) : '',
    });
    const messageForm = useForm({ body: '', is_internal: false });

    const updateTicket = (event) => {
        event.preventDefault();
        updateForm.put(route('superadmin.tickets.update', ticket.id), { preserveScroll: true });
    };

    const addMessage = (event) => {
        event.preventDefault();
        messageForm.post(route('superadmin.tickets.messages.store', ticket.id), {
            preserveScroll: true,
            onSuccess: () => messageForm.reset(),
        });
    };

    const overdue = ticket.sla_due_at && ['open', 'pending'].includes(ticket.status) && new Date(ticket.sla_due_at) < new Date();

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${ticket.number} · ${ticket.subject}`}
                    subtitle={`${ticket.tenant?.company_name || 'Tenant'} support case`}
                    actions={<Link href={route('superadmin.tickets.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to tickets</Link>}
                />
            }
        >
            <Head title={ticket.number} />

            <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
                <div className="space-y-6">
                    <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex gap-2"><StatusBadge status={ticket.status} /><StatusBadge status={ticket.priority} /></div>
                            {overdue && <span className="rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700">SLA overdue</span>}
                        </div>
                        <p className="mt-6 whitespace-pre-wrap text-sm leading-7 text-slate-700">{ticket.description}</p>
                        <dl className="mt-6 grid gap-5 border-t border-slate-100 pt-6 sm:grid-cols-2 lg:grid-cols-3">
                            <Detail label="Tenant">
                                {ticket.tenant ? <Link href={route('superadmin.tenants.show', ticket.tenant.public_uuid || ticket.tenant.id)} className="text-blue-700 hover:text-blue-800">{ticket.tenant.company_name}</Link> : '-'}
                            </Detail>
                            <Detail label="Requester">{ticket.requester_name || ticket.requester_email}</Detail>
                            <Detail label="Category">{ticket.category}</Detail>
                            <Detail label="Assigned to">{ticket.assignee?.name || 'Unassigned'}</Detail>
                            <Detail label="SLA due">{ticket.sla_due_at ? new Date(ticket.sla_due_at).toLocaleString() : '-'}</Detail>
                            <Detail label="Created">{ticket.created_at ? new Date(ticket.created_at).toLocaleString() : '-'}</Detail>
                        </dl>
                    </section>

                    <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-base font-bold text-slate-950">Conversation and internal notes</h2>
                        <div className="mt-5 space-y-4">
                            {(ticket.messages || []).length ? ticket.messages.map((message) => (
                                <article key={message.id} className={`rounded-lg border p-4 ${message.is_internal ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50'}`}>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="text-sm font-semibold text-slate-800">{message.central_user?.name || 'System'} {message.is_internal && <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700">Internal</span>}</div>
                                        <time className="text-xs text-slate-500">{new Date(message.created_at).toLocaleString()}</time>
                                    </div>
                                    <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{message.body}</p>
                                </article>
                            )) : <div className="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">No replies or notes yet.</div>}
                        </div>

                        {canManage && (
                            <form onSubmit={addMessage} className="mt-6 border-t border-slate-100 pt-6">
                                <textarea className={`${inputClass} min-h-32`} placeholder="Write a customer-visible reply or an internal note" value={messageForm.data.body} onChange={(event) => messageForm.setData('body', event.target.value)} />
                                {messageForm.errors.body && <p className="mt-1 text-xs font-semibold text-rose-600">{messageForm.errors.body}</p>}
                                <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" checked={messageForm.data.is_internal} onChange={(event) => messageForm.setData('is_internal', event.target.checked)} className="rounded border-slate-300 text-slate-950 focus:ring-slate-950" />
                                        Internal note
                                    </label>
                                    <button disabled={messageForm.processing} className="rounded-md bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50">
                                        {messageForm.processing ? 'Adding...' : messageForm.data.is_internal ? 'Add internal note' : 'Add reply'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </section>
                </div>

                <aside>
                    <form onSubmit={updateTicket} className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-base font-bold text-slate-950">Ticket controls</h2>
                        <div className="mt-5 space-y-4">
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Subject</span>
                                <input disabled={!canManage} className={`${inputClass} mt-2`} value={updateForm.data.subject} onChange={(event) => updateForm.setData('subject', event.target.value)} />
                            </label>
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Status</span>
                                <select disabled={!canManage} className={`${inputClass} mt-2`} value={updateForm.data.status} onChange={(event) => updateForm.setData('status', event.target.value)}>
                                    {['open', 'pending', 'resolved', 'closed'].map((status) => <option key={status} value={status}>{status}</option>)}
                                </select>
                            </label>
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Priority</span>
                                <select disabled={!canManage} className={`${inputClass} mt-2`} value={updateForm.data.priority} onChange={(event) => updateForm.setData('priority', event.target.value)}>
                                    {['low', 'normal', 'high', 'urgent'].map((priority) => <option key={priority} value={priority}>{priority}</option>)}
                                </select>
                            </label>
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Assignee</span>
                                <select disabled={!canManage} className={`${inputClass} mt-2`} value={updateForm.data.assigned_to} onChange={(event) => updateForm.setData('assigned_to', event.target.value)}>
                                    <option value="">Unassigned</option>
                                    {administrators.map((admin) => <option key={admin.id} value={admin.id}>{admin.name}</option>)}
                                </select>
                            </label>
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Category</span>
                                <input disabled={!canManage} className={`${inputClass} mt-2`} value={updateForm.data.category} onChange={(event) => updateForm.setData('category', event.target.value)} />
                            </label>
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">SLA due</span>
                                <input disabled={!canManage} type="datetime-local" className={`${inputClass} mt-2`} value={updateForm.data.sla_due_at} onChange={(event) => updateForm.setData('sla_due_at', event.target.value)} />
                            </label>
                            {Object.keys(updateForm.errors).length > 0 && <p className="text-xs font-semibold text-rose-600">Please correct the highlighted ticket fields.</p>}
                            <button disabled={!canManage || updateForm.processing} className="w-full rounded-md bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                                {updateForm.processing ? 'Saving...' : 'Save ticket'}
                            </button>
                        </div>
                    </form>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
