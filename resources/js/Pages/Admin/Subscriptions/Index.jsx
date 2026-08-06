import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

const STATUSES = ['trial', 'active', 'past_due', 'cancelled', 'expired', 'suspended', 'manual'];

export default function Index({ subscriptions, plans = [], filters = {} }) {
    const { data, setData } = useForm({
        status: filters.status || '',
        plan_id: filters.plan_id || '',
    });
    const rows = subscriptions?.data || [];

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('superadmin.subscriptions.index'), data, { preserveState: true, preserveScroll: true });
    };

    const columns = [
        {
            title: 'Tenant',
            dataIndex: ['tenant', 'company_name'],
            render: (value, subscription) => (
                <Link href={route('superadmin.subscriptions.show', subscription.public_uuid || subscription.id)} className="font-semibold text-slate-950 hover:text-blue-700">
                    {value || 'Unknown tenant'}
                </Link>
            ),
        },
        { title: 'Plan', dataIndex: ['plan', 'name'], render: (value) => value || '-' },
        { title: 'Status', dataIndex: 'status', render: (status) => <StatusBadge status={status} /> },
        { title: 'Billing', dataIndex: 'billing_interval', render: (value) => value || '-' },
        { title: 'Current period ends', dataIndex: 'current_period_ends_at', render: (value) => value || '-' },
    ];

    return (
        <AuthenticatedLayout header={<PageHeader title="Subscriptions" subtitle="Review tenant billing state and lifecycle dates." />}>
            <Head title="Subscriptions" />
            <form onSubmit={applyFilters} className="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[220px_220px_auto]">
                <select value={data.status} onChange={(event) => setData('status', event.target.value)} className="rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950">
                    <option value="">All statuses</option>
                    {STATUSES.map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
                <select value={data.plan_id} onChange={(event) => setData('plan_id', event.target.value)} className="rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950">
                    <option value="">All plans</option>
                    {plans.map((plan) => <option key={plan.id} value={plan.id}>{plan.name}</option>)}
                </select>
                <button className="rounded-md bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Filter</button>
            </form>
            <DataTable columns={columns} dataSource={rows} />
            <Pagination links={subscriptions?.links} />
        </AuthenticatedLayout>
    );
}
