import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import FilterBar from '@/Components/UI/FilterBar';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useState } from 'react';

const money = value => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Index({ refunds, filters = {}, stats = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');
    const apply = next => router.get(route('superadmin.billing.refunds.index'), { search, status, from, to, ...next }, { preserveState: true, replace: true });
    const columns = [
        { title: 'Processed', dataIndex: 'processed_at', render: value => value ? new Date(value).toLocaleString() : '—' },
        { title: 'Account / service', dataIndex: 'payment', render: payment => <div><p className="font-semibold">{payment?.customer_account?.name || payment?.tenant?.company_name || 'Unassigned'}</p><p className="text-xs text-slate-500">{payment?.tenant?.company_name || '—'}</p></div> },
        { title: 'Payment', dataIndex: 'payment', render: payment => payment ? <Link className="font-mono text-brand-700" href={route('superadmin.billing.payments.show', payment.id)}>{payment.provider_reference || payment.id.slice(0, 8)}</Link> : '—' },
        { title: 'Invoice', dataIndex: ['payment', 'invoice', 'number'], render: value => value || '—' },
        { title: 'Original', dataIndex: ['payment', 'amount'], render: (value, refund) => <span className="font-mono">{refund.payment?.currency || ''} {money(value)}</span> },
        { title: 'This refund', dataIndex: 'amount', render: (value, refund) => <span className="font-mono text-rose-700">{refund.payment?.currency || ''} {money(value)}</span> },
        { title: 'Total refunded', dataIndex: ['payment', 'refunded_amount'], render: (value, refund) => <span className="font-mono">{refund.payment?.currency || ''} {money(value)}</span> },
        { title: 'Available', dataIndex: 'id', render: (_, refund) => <span className="font-mono">{refund.payment?.currency || ''} {money(Math.max(0, Number(refund.payment?.amount || 0) - Number(refund.payment?.refunded_amount || 0)))}</span> },
        { title: 'Status', dataIndex: 'status', render: value => <StatusBadge status={value} /> },
        { title: 'Reason', dataIndex: 'reason', render: value => <span className="block max-w-xs truncate" title={value}>{value}</span> },
        { title: 'Processed by', dataIndex: ['creator', 'name'], render: value => value || 'System' },
    ];
    return <AuthenticatedLayout header={<PageHeader title="Refunds" subtitle="A complete, immutable register of refunds recorded against platform payments." />}><Head title="Refunds" />
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><StatCard title="Refund records" value={stats.total || 0} /><StatCard title="Completed" value={stats.completed || 0} tone="emerald" /><StatCard title={`Refunded (${stats.currency || 'USD'})`} value={money(stats.amount)} tone="rose" /><StatCard title="This month" value={money(stats.thisMonth)} tone="amber" /></div>
        <div className="my-5 rounded-lg border border-slate-200 bg-white p-4"><FilterBar><SearchInput value={search} onChange={setSearch} placeholder="Account, service, reference, reason" className="w-full max-w-xs" /><Select value={status} onChange={event => { setStatus(event.target.value); apply({ status: event.target.value }); }}><option value="">All statuses</option><option value="completed">Completed</option><option value="pending">Pending</option><option value="failed">Failed</option></Select><input type="date" value={from} onChange={event => setFrom(event.target.value)} className="rounded-lg border-slate-300 text-sm" aria-label="From date" /><input type="date" value={to} onChange={event => setTo(event.target.value)} className="rounded-lg border-slate-300 text-sm" aria-label="To date" /><Button size="sm" variant="secondary" onClick={() => apply({})}>Apply</Button></FilterBar></div>
        {(refunds.data || []).length ? <><DataTable columns={columns} dataSource={refunds.data} rowKey="id" /><Pagination links={refunds.links} /></> : <EmptyState icon={RotateCcw} title="No refunds found" description="Refunds recorded from a payment will appear here." />}
    </AuthenticatedLayout>;
}
