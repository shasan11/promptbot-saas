import PageHeader from '@/Components/Superadmin/PageHeader';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

const providers = ['manual', 'bank_transfer', 'stripe', 'paypal', 'khalti', 'esewa'];

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
    const selectedTenant = tenants.find((tenant) => tenant.id === data.tenant_id);
    const selectedInvoice = tenantInvoices.find((invoice) => invoice.id === data.invoice_id);
    const currencyMismatch = selectedInvoice && selectedInvoice.currency && selectedInvoice.currency !== data.currency;

    const changeTenant = (tenantId) => setData({ ...data, tenant_id: tenantId, invoice_id: '', subscription_id: '' });

    const submit = (event) => {
        event.preventDefault();
        editing ? put(route('superadmin.billing.payments.update', payment.id)) : post(route('superadmin.billing.payments.store'));
    };

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={editing ? 'Edit payment' : 'Record payment'}
                    subtitle="Link a payment to its tenant, invoice, or subscription and track its settlement state."
                    actions={<Button href={editing ? route('superadmin.billing.payments.show', payment.id) : route('superadmin.billing.payments.index')} variant="secondary">Back</Button>}
                />
            )}
        >
            <Head title={editing ? 'Edit payment' : 'Record payment'} />

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6">
                <SectionCard title="1. Tenant and linkage" description="Select who this payment belongs to, and optionally the invoice or subscription it settles.">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="tenant_id" label="Tenant" required error={errors.tenant_id}>
                            <Select id="tenant_id" value={data.tenant_id} error={!!errors.tenant_id} onChange={(event) => changeTenant(event.target.value)}>
                                <option value="">Select tenant</option>
                                {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="provider" label="Provider" required error={errors.provider}>
                            <Select id="provider" value={data.provider} onChange={(event) => setData('provider', event.target.value)}>
                                {providers.map((provider) => <option key={provider} value={provider}>{provider.replaceAll('_', ' ')}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="invoice_id" label="Invoice" optional error={errors.invoice_id} hint="Only invoices belonging to the selected tenant are shown.">
                            <Select id="invoice_id" value={data.invoice_id} onChange={(event) => setData('invoice_id', event.target.value)}>
                                <option value="">No invoice</option>
                                {tenantInvoices.map((invoice) => <option key={invoice.id} value={invoice.id}>{invoice.number} · {invoice.currency} {invoice.total} · {invoice.status}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="subscription_id" label="Subscription" optional error={errors.subscription_id} hint="Link recurring or manual subscription payments.">
                            <Select id="subscription_id" value={data.subscription_id} onChange={(event) => setData('subscription_id', event.target.value)}>
                                <option value="">No subscription</option>
                                {tenantSubscriptions.map((subscription) => <option key={subscription.id} value={subscription.id}>{subscription.plan?.name || 'Plan'} · {subscription.status}</option>)}
                            </Select>
                        </FormField>
                    </div>
                </SectionCard>

                <SectionCard title="2. Reference and amount" description="Enter the settlement reference and the amount actually collected.">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="provider_reference" label="Provider reference" optional error={errors.provider_reference}>
                            <Input id="provider_reference" value={data.provider_reference} onChange={(event) => setData('provider_reference', event.target.value)} placeholder="Transaction ID, bank reference, or receipt number" />
                        </FormField>
                        <FormField id="status" label="Status" required error={errors.status}>
                            <Select id="status" value={data.status} onChange={(event) => setData('status', event.target.value)}>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                            </Select>
                        </FormField>
                        <FormField id="amount" label="Amount" required error={errors.amount}>
                            <Input id="amount" type="number" min="0.01" step="0.01" value={data.amount} error={!!errors.amount} onChange={(event) => setData('amount', event.target.value)} />
                        </FormField>
                        <FormField id="currency" label="Currency" required error={errors.currency}>
                            <Input id="currency" maxLength={3} value={data.currency} error={!!errors.currency} onChange={(event) => setData('currency', event.target.value.toUpperCase())} />
                        </FormField>
                        {data.status === 'paid' && (
                            <FormField id="paid_at" label="Paid at" required error={errors.paid_at} className="md:col-span-2">
                                <Input id="paid_at" type="datetime-local" value={data.paid_at} onChange={(event) => setData('paid_at', event.target.value)} />
                            </FormField>
                        )}
                        {data.status === 'failed' && (
                            <FormField id="failure_reason" label="Failure reason" required error={errors.failure_reason} className="md:col-span-2">
                                <Textarea id="failure_reason" value={data.failure_reason} onChange={(event) => setData('failure_reason', event.target.value)} />
                            </FormField>
                        )}
                    </div>

                    {currencyMismatch && (
                        <Alert tone="warning" title="Currency mismatch" className="mt-5">
                            This payment is in {data.currency}, but invoice {selectedInvoice.number} is in {selectedInvoice.currency}. Settlement calculations assume matching currencies.
                        </Alert>
                    )}
                </SectionCard>

                <SectionCard title="3. Review" description="Confirm the record before saving.">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt className="text-slate-500">Tenant</dt><dd className="font-medium text-slate-900">{selectedTenant?.company_name || '—'}</dd></div>
                        <div><dt className="text-slate-500">Amount</dt><dd className="font-mono font-medium text-slate-900">{data.currency} {Number(data.amount || 0).toFixed(2)}</dd></div>
                        <div><dt className="text-slate-500">Provider</dt><dd className="font-medium capitalize text-slate-900">{data.provider.replaceAll('_', ' ')}</dd></div>
                        <div><dt className="text-slate-500">Status</dt><dd className="font-medium capitalize text-slate-900">{data.status}</dd></div>
                    </dl>
                </SectionCard>

                <div className="sticky bottom-0 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6">
                    <div className="flex justify-end gap-3">
                        <Button href={editing ? route('superadmin.billing.payments.show', payment.id) : route('superadmin.billing.payments.index')} variant="secondary">Cancel</Button>
                        <Button type="submit" variant="brand" loading={processing}>{editing ? 'Update payment' : 'Record payment'}</Button>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
