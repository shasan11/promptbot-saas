import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import DescriptionList from '@/Components/UI/DescriptionList';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Lock, MessageSquare, MessagesSquare } from 'lucide-react';

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
    const messageForm = useForm({ body: '', is_internal: false, attachment: null });

    const updateTicket = (event) => {
        event.preventDefault();
        updateForm.put(route('superadmin.tickets.update', ticket.id), { preserveScroll: true });
    };

    const addMessage = (event) => {
        event.preventDefault();
        messageForm.post(route('superadmin.tickets.messages.store', ticket.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => messageForm.reset('body', 'attachment'),
        });
    };

    const overdue = ticket.sla_due_at && ['open', 'pending'].includes(ticket.status) && new Date(ticket.sla_due_at) < new Date();

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={`${ticket.number} · ${ticket.subject}`}
                    subtitle={`${ticket.tenant?.company_name || 'Tenant'} support case`}
                    actions={<Button href={route('superadmin.tickets.index')} variant="secondary">Back to tickets</Button>}
                />
            )}
        >
            <Head title={ticket.number} />

            <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                <div className="space-y-6">
                    <SectionCard>
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex gap-2"><StatusBadge status={ticket.status} /><StatusBadge status={ticket.priority} /></div>
                            {overdue && <Badge tone="danger">SLA overdue</Badge>}
                        </div>
                        <p className="mt-6 whitespace-pre-wrap text-sm leading-7 text-slate-700">{ticket.description}</p>
                        <div className="mt-6 border-t border-slate-100 pt-6">
                            <DescriptionList
                                columns={3}
                                items={[
                                    { label: 'Tenant', value: ticket.tenant ? <Link href={route('superadmin.tenants.show', ticket.tenant.public_uuid || ticket.tenant.id)} className="text-navy-800 hover:text-brand-700">{ticket.tenant.company_name}</Link> : null },
                                    { label: 'Requester', value: ticket.requester_name || ticket.requester_email },
                                    { label: 'Category', value: ticket.category },
                                    { label: 'Assigned to', value: ticket.assignee?.name || 'Unassigned' },
                                    { label: 'SLA due', value: ticket.sla_due_at ? new Date(ticket.sla_due_at).toLocaleString() : null },
                                    { label: 'Created', value: ticket.created_at ? new Date(ticket.created_at).toLocaleString() : null },
                                ]}
                            />
                        </div>
                    </SectionCard>

                    <SectionCard title="Conversation and internal notes">
                        <div className="space-y-4">
                            {(ticket.messages || []).length ? ticket.messages.map((message) => (
                                <article key={message.id} className={`rounded-lg border p-4 ${message.is_internal ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50'}`}>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                            {message.central_user?.name || 'System'}
                                            {message.is_internal && <Badge tone="warning"><Lock className="h-3 w-3" /> Internal</Badge>}
                                        </div>
                                        <time className="text-xs text-slate-500">{new Date(message.created_at).toLocaleString()}</time>
                                    </div>
                                    <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{message.body}</p>
                                    {message.has_attachment && <a href={route('superadmin.tickets.attachments.download', [ticket.id, message.id])} className="mt-3 inline-block rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-indigo-700">Download {message.attachment_name || 'attachment'}{message.attachment_size ? ` · ${Math.ceil(message.attachment_size / 1024)} KB` : ''}</a>}
                                </article>
                            )) : <EmptyState icon={MessagesSquare} title="No replies or notes yet" />}
                        </div>

                        {canManage && (
                            <form onSubmit={addMessage} className="mt-6 border-t border-slate-100 pt-6">
                                <FormField label="Compose" error={messageForm.errors.body}>
                                    <Textarea rows={5} placeholder="Write a customer-visible reply or an internal note" value={messageForm.data.body} error={!!messageForm.errors.body} onChange={(event) => messageForm.setData('body', event.target.value)} />
                                </FormField>
                                <FormField label="Attachment" error={messageForm.errors.attachment}>
                                    <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.csv" onChange={(event) => messageForm.setData('attachment', event.target.files[0] || null)} className="block w-full text-sm" />
                                </FormField>
                                <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <div className="inline-flex rounded-md border border-slate-200 p-0.5" role="radiogroup" aria-label="Message type">
                                        <button
                                            type="button"
                                            onClick={() => messageForm.setData('is_internal', false)}
                                            aria-pressed={!messageForm.data.is_internal}
                                            className={`flex items-center gap-1.5 rounded px-3 py-1.5 text-xs font-semibold transition ${!messageForm.data.is_internal ? 'bg-navy-800 text-white' : 'text-slate-600 hover:bg-slate-50'}`}
                                        >
                                            <MessageSquare className="h-3.5 w-3.5" /> Customer reply
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => messageForm.setData('is_internal', true)}
                                            aria-pressed={messageForm.data.is_internal}
                                            className={`flex items-center gap-1.5 rounded px-3 py-1.5 text-xs font-semibold transition ${messageForm.data.is_internal ? 'bg-amber-500 text-white' : 'text-slate-600 hover:bg-slate-50'}`}
                                        >
                                            <Lock className="h-3.5 w-3.5" /> Internal note
                                        </button>
                                    </div>
                                    <Button type="submit" variant={messageForm.data.is_internal ? 'secondary' : 'brand'} loading={messageForm.processing}>
                                        {messageForm.data.is_internal ? 'Add internal note' : 'Send reply'}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </SectionCard>
                </div>

                <aside>
                    <form onSubmit={updateTicket} className="sticky top-20 rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                        <h2 className="text-sm font-semibold text-slate-900">Ticket controls</h2>
                        <div className="mt-5 space-y-4">
                            <FormField label="Subject">
                                <Input disabled={!canManage} value={updateForm.data.subject} onChange={(event) => updateForm.setData('subject', event.target.value)} />
                            </FormField>
                            <FormField label="Status">
                                <Select disabled={!canManage} value={updateForm.data.status} onChange={(event) => updateForm.setData('status', event.target.value)}>
                                    {['open', 'pending', 'resolved', 'closed'].map((status) => <option key={status} value={status}>{status}</option>)}
                                </Select>
                            </FormField>
                            <FormField label="Priority">
                                <Select disabled={!canManage} value={updateForm.data.priority} onChange={(event) => updateForm.setData('priority', event.target.value)}>
                                    {['low', 'normal', 'high', 'urgent'].map((priority) => <option key={priority} value={priority}>{priority}</option>)}
                                </Select>
                            </FormField>
                            <FormField label="Assignee">
                                <Select disabled={!canManage} value={updateForm.data.assigned_to} onChange={(event) => updateForm.setData('assigned_to', event.target.value)}>
                                    <option value="">Unassigned</option>
                                    {administrators.map((admin) => <option key={admin.id} value={admin.id}>{admin.name}</option>)}
                                </Select>
                            </FormField>
                            <FormField label="Category">
                                <Input disabled={!canManage} value={updateForm.data.category} onChange={(event) => updateForm.setData('category', event.target.value)} />
                            </FormField>
                            <FormField label="SLA due">
                                <Input disabled={!canManage} type="datetime-local" value={updateForm.data.sla_due_at} onChange={(event) => updateForm.setData('sla_due_at', event.target.value)} />
                            </FormField>
                            {Object.keys(updateForm.errors).length > 0 && <p className="text-xs font-semibold text-rose-600">Please correct the highlighted ticket fields.</p>}
                            <Button type="submit" variant="brand" disabled={!canManage} loading={updateForm.processing} className="w-full">Save ticket</Button>
                        </div>
                    </form>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
