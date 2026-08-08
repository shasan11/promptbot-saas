import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import DangerConfirmDialog from '@/Components/UI/DangerConfirmDialog';
import DescriptionList from '@/Components/UI/DescriptionList';
import EmptyState from '@/Components/UI/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CreditCard, Inbox } from 'lucide-react';
import { useState } from 'react';

const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Show({ invoice }) {
    const { auth } = usePage().props;
    const items = invoice.items || [];
    const payments = invoice.payments || [];
    const canManage = auth?.permissions?.includes('invoices.manage');
    const canChangeStatus = canManage && invoice.status !== 'paid' && invoice.status !== 'void';
    const canRecordPayment = auth?.permissions?.includes('payments.manage') && invoice.status !== 'void';
    const netPaid = payments.reduce((sum, payment) => sum + Number(payment.amount || 0) - Number(payment.refunded_amount || 0), 0);
    const balance = Math.max(0, Number(invoice.total) - netPaid);
    const [markPaidOpen, setMarkPaidOpen] = useState(false);
    const [voidOpen, setVoidOpen] = useState(false);

    const paymentColumns = [
        {
            title: 'Reference',
            dataIndex: 'provider_reference',
            render: (value, payment) => <Link href={route('superadmin.billing.payments.show', payment.id)} className="font-mono text-sm font-semibold text-navy-800 hover:text-brand-700">{value || payment.id.slice(0, 8)}</Link>,
        },
        { title: 'Provider', dataIndex: 'provider', render: (value) => value.replaceAll('_', ' ') },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Amount', dataIndex: 'amount', render: (value, payment) => `${payment.currency} ${money(value)}` },
        { title: 'Refunded', dataIndex: 'refunded_amount', render: (value, payment) => `${payment.currency} ${money(value)}` },
        { title: 'Paid at', dataIndex: 'paid_at', render: (value) => value ? new Date(value).toLocaleString() : '—' },
    ];

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={`Invoice ${invoice.number}`}
                    subtitle={invoice.tenant?.company_name || 'Unknown tenant'}
                    actions={(
                        <div className="flex flex-wrap gap-2">
                            {canRecordPayment && <Button href={route('superadmin.billing.payments.create')} variant="brand" icon={CreditCard}>Record payment</Button>}
                            {canChangeStatus && (
                                <>
                                    <Button variant="secondary" onClick={() => setMarkPaidOpen(true)}>Mark as paid</Button>
                                    <Button variant="danger" onClick={() => setVoidOpen(true)}>Void</Button>
                                </>
                            )}
                            <Button href={route('superadmin.billing.invoices.index')} variant="ghost">Back</Button>
                        </div>
                    )}
                />
            )}
        >
            <Head title={`Invoice ${invoice.number}`} />

            <div className={`overflow-hidden rounded-lg border shadow-soft ${invoice.status === 'void' ? 'border-slate-300 bg-slate-50 opacity-75' : 'border-slate-200 bg-white'} print:shadow-none`}>
                <div className="border-b border-slate-100 px-6 py-5">
                    <DescriptionList
                        columns={4}
                        items={[
                            { label: 'Status', value: <StatusBadge status={invoice.status} /> },
                            { label: 'Issued', value: invoice.issued_on },
                            { label: 'Due', value: invoice.due_on || '—' },
                            { label: 'Paid at', value: invoice.paid_at || '—' },
                        ]}
                    />
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Description</th>
                                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Qty</th>
                                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Unit amount</th>
                                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-6 py-4 text-slate-700">{item.description}</td>
                                    <td className="px-6 py-4 text-right text-slate-700">{item.quantity}</td>
                                    <td className="px-6 py-4 text-right font-mono text-slate-700">{invoice.currency} {money(item.unit_amount)}</td>
                                    <td className="px-6 py-4 text-right font-mono font-semibold text-slate-900">{invoice.currency} {money(item.total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="ml-auto max-w-xs space-y-2 px-6 py-5 text-sm">
                    <div className="flex justify-between text-slate-600"><span>Subtotal</span><span className="font-mono font-semibold text-slate-900">{invoice.currency} {money(invoice.subtotal)}</span></div>
                    <div className="flex justify-between text-slate-600"><span>Tax</span><span className="font-mono font-semibold text-slate-900">{invoice.currency} {money(invoice.tax_total)}</span></div>
                    <div className="flex justify-between border-t border-slate-200 pt-2 text-base"><span className="font-bold text-slate-900">Total</span><span className="font-mono font-bold text-slate-900">{invoice.currency} {money(invoice.total)}</span></div>
                    <div className="flex justify-between text-slate-600"><span>Net paid</span><span className="font-mono font-semibold text-brand-700">{invoice.currency} {money(netPaid)}</span></div>
                    <div className="flex justify-between text-slate-600"><span>Remaining balance</span><span className="font-mono font-semibold text-slate-900">{invoice.currency} {money(balance)}</span></div>
                </div>
            </div>

            <div className="mt-6">
                <SectionCard title="Payment history" description="Settlement status uses the net amount after all completed refunds.">
                    {payments.length ? <DataTable columns={paymentColumns} dataSource={payments} rowKey="id" /> : <EmptyState icon={Inbox} title="No payments recorded yet" />}
                </SectionCard>
            </div>

            <ConfirmDialog
                open={markPaidOpen}
                title={`Mark invoice ${invoice.number} as paid?`}
                confirmLabel="Mark as paid"
                onCancel={() => setMarkPaidOpen(false)}
                onConfirm={() => { router.post(route('superadmin.billing.invoices.mark-paid', invoice.id), {}, { preserveScroll: true }); setMarkPaidOpen(false); }}
            >
                This marks the invoice paid without recording an actual payment. Use "Record payment" instead if money was received through a provider.
            </ConfirmDialog>

            <DangerConfirmDialog
                open={voidOpen}
                title={`Void invoice ${invoice.number}`}
                consequence="Voiding removes this invoice from collections and outstanding balances. It will no longer be payable."
                affected={invoice.number}
                reversible={false}
                confirmLabel="Void invoice"
                onCancel={() => setVoidOpen(false)}
                onConfirm={() => { router.post(route('superadmin.billing.invoices.void', invoice.id), {}, { preserveScroll: true }); setVoidOpen(false); }}
            />
        </AuthenticatedLayout>
    );
}
