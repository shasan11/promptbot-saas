import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ plans }) {
    const rows = plans?.data || [];

    const archive = (plan) => {
        if (!window.confirm(`Archive the "${plan.name}" plan? It stays available for existing subscribers' history but new subscriptions can no longer use it.`)) {
            return;
        }

        router.delete(route('superadmin.plans.destroy', plan.public_uuid || plan.id), { preserveScroll: true });
    };

    const columns = [
        {
            title: 'Plan',
            dataIndex: 'name',
            render: (value, plan) => (
                <div>
                    <Link href={route('superadmin.plans.show', plan.public_uuid || plan.id)} className="font-semibold text-slate-950 hover:text-blue-700">{value}</Link>
                    <div className="mt-1 text-xs text-slate-500">{plan.slug}</div>
                </div>
            ),
        },
        { title: 'Monthly', dataIndex: 'monthly_price', render: (value, plan) => `${plan.currency} ${value}` },
        { title: 'Annual', dataIndex: 'annual_price', render: (value, plan) => `${plan.currency} ${value}` },
        { title: 'Status', dataIndex: 'is_active', render: (active) => <StatusBadge status={active ? 'active' : 'inactive'} /> },
        { title: 'Recommended', dataIndex: 'is_recommended', render: (value) => value ? 'Yes' : '-' },
        {
            title: '',
            dataIndex: 'id',
            render: (_, plan) => (
                <div className="flex justify-end gap-2 text-right">
                    <Link href={route('superadmin.plans.edit', plan.public_uuid || plan.id)} className="text-sm font-semibold text-slate-600 hover:text-slate-950">Edit</Link>
                    <button type="button" onClick={() => archive(plan)} className="text-sm font-semibold text-rose-600 hover:text-rose-800">Archive</button>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Plans"
                    subtitle="Manage pricing packages, catalog order, and plan visibility."
                    actions={<Link href={route('superadmin.plans.create')} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Create plan</Link>}
                />
            }
        >
            <Head title="Plans" />
            <DataTable columns={columns} dataSource={rows} />
            <Pagination links={plans?.links} />
        </AuthenticatedLayout>
    );
}
