import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950';
const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function Detail({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</dt>
            <dd className="mt-1 text-sm font-semibold text-slate-800">{children || '-'}</dd>
        </div>
    );
}

export default function Show({ payment, refundableAmount = 0 }) {
    const { auth } = usePage().props;
    const canManage = auth?.permissions?.includes('payments.manage');
    const { data, setData, post, processing, errors, reset } = useForm({ amount: '', reason: '', provider_reference: '' });

    const refund = (event) => {
        event.preventDefault();
        post(route('superadmin.billing.payments.refund', payment.id), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const refundColumns = [
        { title: 'Amount', dataIndex: 'amount', render: (value) => `${payment.currency} ${money(value)}` },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Reason', dataIndex: 'reason' },
        { title: 'Reference', dataIndex: 'provider_reference', render: (value) => value || '-' },
        { title: 'Processed by', dataIndex: ['creator', 'name'], render: (value) => value || 'System' },
        { title: 'Processed', dataIndex: 'processed_at', render: (value) => value ? new Date(value).toLocaleString() : '-' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Payment Details"
                    subtitle={payment.provider_reference || payment.id}
                    actions={
                        <div className="flex gap-2">
                            <Link href={route('superadmin.billing.payments.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back</Link>
                            {canManage && <Link href={route('superadmin.billing.payments.edit', payment.id)} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Edit</Link>}
                        </div>
                    }
                />
            }
        >
            <Head title="Payment Details" />

            <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                <div className="space-y-6">
                    <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="mb-6 flex items-start justify-between gap-4">
                            <div>
                                <p className="text-sm font-semibold capitalize text-slate-500">{payment.provider.replaceAll('_', ' ')}</p>
                                <p className="mt-1 text-3xl font-bold text-slate-950">{payment.currency} {money(payment.amount)}</p>
                            </div>
                            <StatusBadge status={payment.status} />
                        </div>
                        <dl className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <Detail label="Tenant">
                                {payment.tenant ? <Link href={route('superadmin.tenants.show', payment.tenant.public_uuid || payment.tenant.id)} className="text-blue-700 hover:text-blue-800">{payment.tenant.company_name}</Link> : '-'}
                            </Detail>
                            <Detail label="Invoice">
                                {payment.invoice ? <Link href={route('superadmin.billing.invoices.show', payment.invoice.id)} className="text-blue-700 hover:text-blue-800">{payment.invoice.number}</Link> : '-'}
                            </Detail>
                            <Detail label="Subscription">{payment.subscription?.plan?.name || '-'}</Detail>
                            <Detail label="Provider reference">{payment.provider_reference}</Detail>
                            <Detail label="Paid at">{payment.paid_at ? new Date(payment.paid_at).toLocaleString() : '-'}</Detail>
                            <Detail label="Recorded by">{payment.creator?.name || 'System'}</Detail>
                            <Detail label="Refunded">{payment.currency} {money(payment.refunded_amount)}</Detail>
                            <Detail label="Refundable">{payment.currency} {money(refundableAmount)}</Detail>
                            <Detail label="Created">{payment.created_at ? new Date(payment.created_at).toLocaleString() : '-'}</Detail>
                        </dl>
                        {payment.failure_reason && <div className="mt-6 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong>Failure reason:</strong> {payment.failure_reason}</div>}
                    </section>

                    <section>
                        <h2 className="mb-3 text-base font-bold text-slate-950">Refund history</h2>
                        <DataTable columns={refundColumns} dataSource={payment.refunds || []} rowKey="id" />
                    </section>
                </div>

                <aside>
                    <form onSubmit={refund} className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-base font-bold text-slate-950">Record refund</h2>
                        <p className="mt-1 text-sm text-slate-500">Available: {payment.currency} {money(refundableAmount)}</p>
                        <div className="mt-5 space-y-4">
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Amount</span>
                                <input disabled={!canManage || refundableAmount <= 0} type="number" min="0.01" max={refundableAmount} step="0.01" className={`${inputClass} mt-2`} value={data.amount} onChange={(event) => setData('amount', event.target.value)} />
                                {errors.amount && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.amount}</p>}
                            </label>
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Reason</span>
                                <textarea disabled={!canManage || refundableAmount <= 0} className={`${inputClass} mt-2 min-h-24`} value={data.reason} onChange={(event) => setData('reason', event.target.value)} />
                                {errors.reason && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.reason}</p>}
                            </label>
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Provider reference</span>
                                <input disabled={!canManage || refundableAmount <= 0} className={`${inputClass} mt-2`} value={data.provider_reference} onChange={(event) => setData('provider_reference', event.target.value)} />
                            </label>
                            <button disabled={processing || !canManage || refundableAmount <= 0} className="w-full rounded-md bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                                {processing ? 'Recording...' : refundableAmount > 0 ? 'Record refund' : 'Fully refunded'}
                            </button>
                        </div>
                    </form>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
