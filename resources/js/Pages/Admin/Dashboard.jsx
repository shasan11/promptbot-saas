import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Dashboard({ currency = 'USD', stats = {}, recentTenants = [], subscriptionsByStatus = [], recentPayments = [], urgentTickets = [] }) {
    const tenantColumns = [
        {
            title: 'Tenant',
            dataIndex: 'company_name',
            render: (value, tenant) => (
                <div>
                    <Link className="font-semibold text-slate-950 hover:text-blue-700" href={route('superadmin.tenants.show', tenant.public_uuid || tenant.id)}>{value}</Link>
                    <div className="mt-1 text-xs text-slate-500">{tenant.slug}</div>
                </div>
            ),
        },
        { title: 'Status', dataIndex: 'status', render: (status) => <StatusBadge status={status} /> },
        { title: 'Plan', dataIndex: ['plan', 'name'], render: (value) => value || '-' },
        {
            title: 'Primary domain',
            dataIndex: 'domains',
            render: (domains = []) => domains.find((domain) => domain.is_primary)?.domain || domains[0]?.domain || '-',
        },
    ];

    const paymentColumns = [
        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '-' },
        { title: 'Invoice', dataIndex: ['invoice', 'number'], render: (value) => value || '-' },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Amount', dataIndex: 'amount', render: (value, payment) => `${payment.currency} ${money(value)}` },
    ];

    const ticketColumns = [
        {
            title: 'Ticket',
            dataIndex: 'number',
            render: (value, ticket) => <Link href={route('superadmin.tickets.show', ticket.id)} className="font-mono text-sm font-semibold text-blue-700 hover:text-blue-800">{value}</Link>,
        },
        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '-' },
        { title: 'Priority', dataIndex: 'priority', render: (value) => <StatusBadge status={value} /> },
        { title: 'Assigned', dataIndex: ['assignee', 'name'], render: (value) => value || 'Unassigned' },
    ];

    return (
        <AuthenticatedLayout header={<PageHeader title="Superadmin Dashboard" subtitle="Live tenant, billing, support, and subscription operations." />}>
            <Head title="Superadmin Dashboard" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
                <StatCard title="Tenants" value={stats.tenants ?? 0} tone="slate" />
                <StatCard title="Active tenants" value={stats.activeTenants ?? 0} tone="emerald" />
                <StatCard title="Active plans" value={stats.plans ?? 0} tone="blue" />
                <StatCard title="Subscriptions" value={stats.activeSubscriptions ?? 0} tone="amber" />
                <StatCard title="Outstanding invoices" value={stats.outstandingInvoices ?? 0} tone="rose" />
                <StatCard title={`Net collected (${currency})`} value={money(stats.netCollected)} tone="emerald" />
                <StatCard title="Open tickets" value={stats.openTickets ?? 0} tone="slate" />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-[1fr_320px]">
                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-base font-bold text-slate-950">Recent tenants</h2>
                        <Link href={route('superadmin.tenants.index')} className="text-sm font-semibold text-blue-700 hover:text-blue-800">View all</Link>
                    </div>
                    <DataTable rowKey="id" columns={tenantColumns} dataSource={recentTenants} />
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-base font-bold text-slate-950">Subscription status</h2>
                    <div className="mt-5 space-y-3">
                        {subscriptionsByStatus.length ? subscriptionsByStatus.map((item) => (
                            <div key={item.status} className="flex items-center justify-between rounded-md bg-slate-50 px-4 py-3">
                                <StatusBadge status={item.status} />
                                <span className="text-lg font-bold text-slate-950">{item.total}</span>
                            </div>
                        )) : <div className="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">No subscriptions yet</div>}
                    </div>
                </section>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-base font-bold text-slate-950">Recent payments</h2>
                        <Link href={route('superadmin.billing.payments.index')} className="text-sm font-semibold text-blue-700 hover:text-blue-800">View payments</Link>
                    </div>
                    <DataTable rowKey="id" columns={paymentColumns} dataSource={recentPayments} />
                </section>
                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-base font-bold text-slate-950">Tickets needing attention</h2>
                        <Link href={route('superadmin.tickets.index')} className="text-sm font-semibold text-blue-700 hover:text-blue-800">View tickets</Link>
                    </div>
                    <DataTable rowKey="id" columns={ticketColumns} dataSource={urgentTickets} />
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
