import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950';
const providers = ['manual', 'bank_transfer', 'stripe', 'paypal', 'khalti', 'esewa'];

function Field({ label, error, hint, children, className = '' }) {
    return (
        <label className={`block ${className}`}>
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            <div className="mt-2">{children}</div>
            {hint && !error && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
            {error && <p className="mt-1 text-xs font-semibold text-rose-600">{error}</p>}
        </label>
    );
}

export default function Create({ payment = null, tenants = [], invoices = [], subscriptions = [], defaults = {} }) {
    const editing = Boolean(payment);
    const initialTenant = payment?.tenant_id || tenants[0]?.id || '';
    const { data, setData, post, put, processing, errors } = useForm({
        tenant_id: initialTenant,
        invoice_id: payment?.invoice_id || '',
        subscription_id: payment?.subscription_id || '',
        provider: payment?.provider || defaults.provider || 'manual',
        provider_reference: payment?.provider_reference || '',
        status: payment?.status || 'pending',
        amount: payment?.amount || '',
        currency: payment?.currency || defaults.currency || 'USD',
        paid_at: payment?.paid_at ? payment.paid_at.slice(0, 16) : '',
        failure_reason: payment?.failure_reason || '',
    });

    const tenantInvoices = invoices.filter((invoice) => invoice.tenant_id === data.tenant_id || invoice.id === payment?.invoice_id);
    const tenantSubscriptions = subscriptions.filter((subscription) => subscription.tenant_id === data.tenant_id || subscription.id === payment?.subscription_id);

    const changeTenant = (tenantId) => {
        setData({ ...data, tenant_id: tenantId, invoice_id: '', subscription_id: '' });
    };

    const submit = (event) => {
        event.preventDefault();
        editing
            ? put(route('superadmin.billing.payments.update', payment.id))
            : post(route('superadmin.billing.payments.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={editing ? 'Edit Payment' : 'Record Payment'}
                    subtitle="Link a payment to its tenant, invoice, or subscription and track its settlement state."
                    actions={<Link href={editing ? route('superadmin.billing.payments.show', payment.id) : route('superadmin.billing.payments.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back</Link>}
                />
            }
        >
            <Head title={editing ? 'Edit Payment' : 'Record Payment'} />

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6">
                <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-5 md:grid-cols-2">
                        <Field label="Tenant" error={errors.tenant_id}>
                            <select className={inputClass} value={data.tenant_id} onChange={(event) => changeTenant(event.target.value)}>
                                <option value="">Select tenant</option>
                                {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                            </select>
                        </Field>
                        <Field label="Provider" error={errors.provider}>
                            <select className={inputClass} value={data.provider} onChange={(event) => setData('provider', event.target.value)}>
                                {providers.map((provider) => <option key={provider} value={provider}>{provider.replaceAll('_', ' ')}</option>)}
                            </select>
                        </Field>
                        <Field label="Invoice" error={errors.invoice_id} hint="Optional. Only invoices belonging to the selected tenant are shown.">
                            <select className={inputClass} value={data.invoice_id} onChange={(event) => setData('invoice_id', event.target.value)}>
                                <option value="">No invoice</option>
                                {tenantInvoices.map((invoice) => <option key={invoice.id} value={invoice.id}>{invoice.number} · {invoice.currency} {invoice.total} · {invoice.status}</option>)}
                            </select>
                        </Field>
                        <Field label="Subscription" error={errors.subscription_id} hint="Optional. Link recurring or manual subscription payments.">
                            <select className={inputClass} value={data.subscription_id} onChange={(event) => setData('subscription_id', event.target.value)}>
                                <option value="">No subscription</option>
                                {tenantSubscriptions.map((subscription) => <option key={subscription.id} value={subscription.id}>{subscription.plan?.name || 'Plan'} · {subscription.status}</option>)}
                            </select>
                        </Field>
                        <Field label="Provider reference" error={errors.provider_reference}>
                            <input className={inputClass} value={data.provider_reference} onChange={(event) => setData('provider_reference', event.target.value)} placeholder="Transaction ID, bank reference, or receipt number" />
                        </Field>
                        <Field label="Status" error={errors.status}>
                            <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                            </select>
                        </Field>
                        <Field label="Amount" error={errors.amount}>
                            <input type="number" min="0.01" step="0.01" className={inputClass} value={data.amount} onChange={(event) => setData('amount', event.target.value)} />
                        </Field>
                        <Field label="Currency" error={errors.currency}>
                            <input maxLength={3} className={inputClass} value={data.currency} onChange={(event) => setData('currency', event.target.value.toUpperCase())} />
                        </Field>
                        {data.status === 'paid' && (
                            <Field label="Paid at" error={errors.paid_at} className="md:col-span-2">
                                <input type="datetime-local" className={inputClass} value={data.paid_at} onChange={(event) => setData('paid_at', event.target.value)} />
                            </Field>
                        )}
                        {data.status === 'failed' && (
                            <Field label="Failure reason" error={errors.failure_reason} className="md:col-span-2">
                                <textarea className={`${inputClass} min-h-28`} value={data.failure_reason} onChange={(event) => setData('failure_reason', event.target.value)} />
                            </Field>
                        )}
                    </div>
                </section>

                <div className="flex justify-end gap-3">
                    <Link href={editing ? route('superadmin.billing.payments.show', payment.id) : route('superadmin.billing.payments.index')} className="rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Cancel</Link>
                    <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {processing ? 'Saving...' : editing ? 'Update payment' : 'Record payment'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
