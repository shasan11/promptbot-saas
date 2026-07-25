import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function SupportShow({ ticket, messages = [] }) {
    return (
        <AuthenticatedLayout header={<PageHeader title={ticket.subject} subtitle={`Ticket ${ticket.id}`} />}>
            <Head title={ticket.subject} />
            <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div className="grid gap-4 sm:grid-cols-4">
                    {['status', 'priority', 'tenant_id', 'sla_due_at'].map((key) => <div key={key}><div className="text-xs font-bold uppercase text-slate-500">{key.replaceAll('_', ' ')}</div><div className="mt-1 text-sm font-semibold">{ticket[key] || '-'}</div></div>)}
                </div>
                <div className="mt-6 space-y-3">{messages.map((message) => <div key={message.id} className="rounded-md bg-slate-50 p-4 text-sm text-slate-700">{message.body}</div>)}</div>
            </section>
        </AuthenticatedLayout>
    );
}
