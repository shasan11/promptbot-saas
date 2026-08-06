import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const providers = ['manual', 'bank_transfer', 'stripe', 'paypal', 'khalti', 'esewa'];
const statuses = ['pending', 'paid', 'failed', 'partially_refunded', 'refunded'];
const inputClass = 'rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950';

const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Index({ payments, tenants = [], filters = {}, stats = {} }) {
    const { auth } = usePage().props;
    const canManage = auth?.permissions?.includes('payments.manage');
    const { data, setData } = useForm({
        search: filters.search || '',
        status: filters.status || '',
        provider: filters.provider || '',
        tenant_id: filters.tenant_id || '',
    });

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('superadmin.billing.payments.index'), data, { preserveState: true, preserveScroll: true });
    };

    const columns = [
        {
            title: 'Payment',
            dataIndex: 'id',
            render: (value, payment) => (
                <div>
                    <Link href={route('superadmin.billing.payments.show', value)} className="font-mono text-sm font-semibold text-slate-950 hover:text-blue-700">
                        {payment.provider_reference || value.slice(0, 8)}
                    </Link>
                    <div className="mt-1 text-xs capitalize text-slate-500">{payment.provider.replaceAll('_', ' ')}</div>
                </div>
            ),
        },
        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '-' },
        { title: 'Invoice', dataIndex: ['invoice', 'number'], render: (value) => value || '-' },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Amount', dataIndex: 'amount', render: (value, payment) => `${payment.currency} ${money(value)}` },
        { title: 'Refunded', dataIndex: 'refunded_amount', render: (value, payment) => `${payment.currency} ${money(value)}` },
        { title: 'Created', dataIndex: 'created_at', render: (value) => value ? new Date(value).toLocaleString() : '-' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Payments"
                    subtitle="Record, reconcile, and refund tenant payments."
                    actions={canManage ? <Link href={route('superadmin.billing.payments.create')} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Record payment</Link> : null}
                />
            }
        >
            <Head title="Payments" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Payment records" value={stats.total ?? 0} tone="slate" />
                <StatCard title="Gross paid" value={money(stats.paid)} tone="emerald" />
                <StatCard title="Pending" value={stats.pending ?? 0} tone="amber" />
                <StatCard title="Refunded" value={money(stats.refunded)} tone="rose" />
            </div>

            <form onSubmit={applyFilters} className="my-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm xl:grid-cols-[1fr_180px_180px_220px_auto]">
                <input className={inputClass} placeholder="Reference, invoice, or tenant" value={data.search} onChange={(event) => setData('search', event.target.value)} />
                <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                    <option value="">All statuses</option>
                    {statuses.map((status) => <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>)}
                </select>
                <select className={inputClass} value={data.provider} onChange={(event) => setData('provider', event.target.value)}>
                    <option value="">All providers</option>
                    {providers.map((provider) => <option key={provider} value={provider}>{provider.replaceAll('_', ' ')}</option>)}
                </select>
                <select className={inputClass} value={data.tenant_id} onChange={(event) => setData('tenant_id', event.target.value)}>
                    <option value="">All tenants</option>
                    {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                </select>
                <button className="rounded-md bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Filter</button>
            </form>

            <DataTable columns={columns} dataSource={payments?.data || []} rowKey="id" />
            <Pagination links={payments?.links} />
        </AuthenticatedLayout>
    );
}
