import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import DescriptionList from '@/Components/UI/DescriptionList';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Textarea from '@/Components/UI/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import { useState } from 'react';

const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Show({ payment, refundableAmount = 0 }) {
    const { auth } = usePage().props;
    const canManage = auth?.permissions?.includes('payments.manage');
    const canEdit = canManage && Number(payment.refunded_amount || 0) === 0;
    const canRefund = canManage && ['paid', 'partially_refunded'].includes(payment.status) && refundableAmount > 0;
    const { data, setData, post, processing, errors, reset } = useForm({ amount: '', reason: '', provider_reference: '', idempotency_key: crypto.randomUUID() });
    const [confirmOpen, setConfirmOpen] = useState(false);
    const remainingAfterRefund = Math.max(0, refundableAmount - Number(data.amount || 0));

    const openConfirm = (event) => {
        event.preventDefault();
        if (!canRefund) return;
        setConfirmOpen(true);
    };

    const submitRefund = () => {
        post(route('superadmin.billing.payments.refund', payment.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset('amount', 'reason', 'provider_reference');
                setData('idempotency_key', crypto.randomUUID());
            },
        });
        setConfirmOpen(false);
    };

    const refundColumns = [
        { title: 'Amount', dataIndex: 'amount', render: (value) => `${payment.currency} ${money(value)}` },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Reason', dataIndex: 'reason' },
        { title: 'Reference', dataIndex: 'provider_reference', render: (value) => value || '—' },
        { title: 'Processed by', dataIndex: ['creator', 'name'], render: (value) => value || 'System' },
        { title: 'Processed', dataIndex: 'processed_at', render: (value) => value ? new Date(value).toLocaleString() : '—' },
    ];

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="Payment details"
                    subtitle={payment.provider_reference || payment.id}
                    actions={(
                        <div className="flex gap-2">
                            <Button href={route('superadmin.billing.payments.index')} variant="secondary">Back</Button>
                            {canEdit && <Button href={route('superadmin.billing.payments.edit', payment.id)} variant="brand">Edit</Button>}
                        </div>
                    )}
                />
            )}
        >
            <Head title="Payment details" />

            <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                <div className="space-y-6">
                    <SectionCard>
                        <div className="mb-6 flex items-start justify-between gap-4">
                            <div>
                                <p className="text-sm font-semibold capitalize text-slate-500">{payment.provider.replaceAll('_', ' ')}</p>
                                <p className="mt-1 font-mono text-3xl font-bold text-slate-900">{payment.currency} {money(payment.amount)}</p>
                            </div>
                            <StatusBadge status={payment.status} />
                        </div>
                        <DescriptionList
                            columns={3}
                            items={[
                                { label: 'Tenant', value: payment.tenant ? <Link href={route('superadmin.tenants.show', payment.tenant.public_uuid || payment.tenant.id)} className="text-navy-800 hover:text-brand-700">{payment.tenant.company_name}</Link> : null },
                                { label: 'Invoice', value: payment.invoice ? <Link href={route('superadmin.billing.invoices.show', payment.invoice.id)} className="text-navy-800 hover:text-brand-700">{payment.invoice.number}</Link> : null },
                                { label: 'Subscription', value: payment.subscription?.plan?.name },
                                { label: 'Provider reference', value: payment.provider_reference },
                                { label: 'Paid at', value: payment.paid_at ? new Date(payment.paid_at).toLocaleString() : null },
                                { label: 'Recorded by', value: payment.creator?.name || 'System' },
                                { label: 'Refunded', value: `${payment.currency} ${money(payment.refunded_amount)}` },
                                { label: 'Refundable', value: `${payment.currency} ${money(refundableAmount)}` },
                                { label: 'Created', value: payment.created_at ? new Date(payment.created_at).toLocaleString() : null },
                            ]}
                        />
                        {payment.failure_reason && <Alert tone="danger" title="Failure reason" className="mt-6">{payment.failure_reason}</Alert>}
                    </SectionCard>

                    <SectionCard title="Refund history">
                        {(payment.refunds || []).length ? <DataTable columns={refundColumns} dataSource={payment.refunds} rowKey="id" /> : <EmptyState icon={Inbox} title="No refunds recorded" />}
                    </SectionCard>
                </div>

                <aside>
                    <form onSubmit={openConfirm} className="rounded-lg border border-amber-200 bg-amber-50/30 p-5 shadow-soft">
                        <h2 className="text-sm font-semibold text-amber-800">Record refund</h2>
                        <p className="mt-1 text-xs text-amber-700/80">Available to refund: {payment.currency} {money(refundableAmount)}</p>
                        <div className="mt-5 space-y-4">
                            <FormField id="refund_amount" label="Amount" required error={errors.amount}>
                                <Input id="refund_amount" disabled={!canRefund} type="number" min="0.01" max={refundableAmount} step="0.01" value={data.amount} error={!!errors.amount} onChange={(event) => setData('amount', event.target.value)} />
                            </FormField>
                            <FormField id="refund_reason" label="Reason" required error={errors.reason} hint="Recorded for the audit trail.">
                                <Textarea id="refund_reason" disabled={!canRefund} value={data.reason} error={!!errors.reason} onChange={(event) => setData('reason', event.target.value)} />
                            </FormField>
                            <FormField id="refund_reference" label="Provider reference" optional>
                                <Input id="refund_reference" disabled={!canRefund} value={data.provider_reference} onChange={(event) => setData('provider_reference', event.target.value)} />
                            </FormField>
                            <Button type="submit" variant="danger" disabled={!canRefund} loading={processing} className="w-full">
                                {canRefund ? 'Record refund' : payment.status === 'refunded' ? 'Fully refunded' : 'Refund unavailable'}
                            </Button>
                        </div>
                    </form>
                </aside>
            </div>

            <ConfirmDialog
                open={confirmOpen}
                title="Confirm refund"
                variant="danger"
                confirmLabel="Record refund"
                processing={processing}
                onCancel={() => setConfirmOpen(false)}
                onConfirm={submitRefund}
            >
                <p>You're about to refund <strong>{payment.currency} {money(data.amount || 0)}</strong> on this payment.</p>
                <p className="mt-2">Remaining refundable balance after this refund: <strong>{payment.currency} {money(remainingAfterRefund)}</strong>.</p>
                <p className="mt-2 text-rose-700">This action cannot be undone.</p>
            </ConfirmDialog>
        </AuthenticatedLayout>
    );
}
