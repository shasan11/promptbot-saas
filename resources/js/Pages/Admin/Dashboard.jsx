import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ stats = {}, recentTenants = [], subscriptionsByStatus = [] }) {
    const columns = [
        {
            title: 'Tenant',
            dataIndex: 'company_name',
            render: (value, tenant) => (
                <div>
                    <Link className="font-semibold text-slate-950 hover:text-blue-700" href={route('superadmin.tenants.show', tenant.public_uuid || tenant.id)}>
                        {value}
                    </Link>
                    <div className="mt-1 text-xs text-slate-500">{tenant.slug}</div>
                </div>
            ),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            render: (status) => <StatusBadge status={status} />,
        },
        {
            title: 'Plan',
            dataIndex: ['plan', 'name'],
            render: (value) => value || '-',
        },
        {
            title: 'Domains',
            dataIndex: 'domains',
            render: (domains = []) => domains.length ? (
                <div className="flex flex-wrap gap-2">
                    {domains.map((domain) => (
                        <span key={domain.id} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                            {domain.domain}
                        </span>
                    ))}
                </div>
            ) : '-',
        },
    ];

    return (
        <AuthenticatedLayout
            header={<PageHeader title="Superadmin Dashboard" subtitle="Operational overview for PromptBot platform owners." />}
        >
            <Head title="Superadmin Dashboard" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard title="Tenants" value={stats.tenants ?? 0} tone="slate" />
                <StatCard title="Active Tenants" value={stats.activeTenants ?? 0} tone="emerald" />
                <StatCard title="Plans" value={stats.plans ?? 0} tone="blue" />
                <StatCard title="Subscriptions" value={stats.subscriptions ?? 0} tone="amber" />
                <StatCard title="Features" value={stats.features ?? 0} tone="rose" />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-base font-bold text-slate-950">Recent tenants</h2>
                        <Link href={route('superadmin.tenants.index')} className="text-sm font-semibold text-blue-700 hover:text-blue-800">
                            View all
                        </Link>
                    </div>
                    <DataTable rowKey="id" columns={columns} dataSource={recentTenants} />
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-base font-bold text-slate-950">Subscription status</h2>
                    <div className="mt-5 space-y-3">
                        {subscriptionsByStatus.length ? subscriptionsByStatus.map((item) => (
                            <div key={item.status} className="flex items-center justify-between rounded-md bg-slate-50 px-4 py-3">
                                <span className="text-sm font-medium capitalize text-slate-600">{item.status}</span>
                                <span className="text-lg font-bold text-slate-950">{item.total}</span>
                            </div>
                        )) : (
                            <div className="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                                No subscriptions yet
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
