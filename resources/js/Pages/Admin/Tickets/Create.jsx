import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950';

function Field({ label, error, children, className = '' }) {
    return (
        <label className={`block ${className}`}>
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            <div className="mt-2">{children}</div>
            {error && <p className="mt-1 text-xs font-semibold text-rose-600">{error}</p>}
        </label>
    );
}

export default function Create({ tenants = [], administrators = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        tenant_id: tenants[0]?.id || '',
        subject: '',
        description: '',
        priority: 'normal',
        category: '',
        assigned_to: '',
        requester_name: '',
        requester_email: '',
        sla_due_at: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('superadmin.tickets.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Create Ticket"
                    subtitle="Open a support case for a tenant and assign its initial priority and SLA."
                    actions={<Link href={route('superadmin.tickets.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to tickets</Link>}
                />
            }
        >
            <Head title="Create Ticket" />

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6">
                <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-5 md:grid-cols-2">
                        <Field label="Tenant" error={errors.tenant_id}>
                            <select className={inputClass} value={data.tenant_id} onChange={(event) => setData('tenant_id', event.target.value)}>
                                <option value="">Select tenant</option>
                                {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                            </select>
                        </Field>
                        <Field label="Assigned administrator" error={errors.assigned_to}>
                            <select className={inputClass} value={data.assigned_to} onChange={(event) => setData('assigned_to', event.target.value)}>
                                <option value="">Unassigned</option>
                                {administrators.map((admin) => <option key={admin.id} value={admin.id}>{admin.name} · {admin.email}</option>)}
                            </select>
                        </Field>
                        <Field label="Subject" error={errors.subject} className="md:col-span-2">
                            <input className={inputClass} value={data.subject} onChange={(event) => setData('subject', event.target.value)} />
                        </Field>
                        <Field label="Description" error={errors.description} className="md:col-span-2">
                            <textarea className={`${inputClass} min-h-40`} value={data.description} onChange={(event) => setData('description', event.target.value)} />
                        </Field>
                        <Field label="Priority" error={errors.priority}>
                            <select className={inputClass} value={data.priority} onChange={(event) => setData('priority', event.target.value)}>
                                {['low', 'normal', 'high', 'urgent'].map((priority) => <option key={priority} value={priority}>{priority}</option>)}
                            </select>
                        </Field>
                        <Field label="Category" error={errors.category}>
                            <input className={inputClass} value={data.category} onChange={(event) => setData('category', event.target.value)} placeholder="Billing, technical, account..." />
                        </Field>
                        <Field label="Requester name" error={errors.requester_name}>
                            <input className={inputClass} value={data.requester_name} onChange={(event) => setData('requester_name', event.target.value)} />
                        </Field>
                        <Field label="Requester email" error={errors.requester_email}>
                            <input type="email" className={inputClass} value={data.requester_email} onChange={(event) => setData('requester_email', event.target.value)} />
                        </Field>
                        <Field label="SLA due" error={errors.sla_due_at} className="md:col-span-2">
                            <input type="datetime-local" className={inputClass} value={data.sla_due_at} onChange={(event) => setData('sla_due_at', event.target.value)} />
                        </Field>
                    </div>
                </section>

                <div className="flex justify-end gap-3">
                    <Link href={route('superadmin.tickets.index')} className="rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Cancel</Link>
                    <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {processing ? 'Creating...' : 'Create ticket'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
