import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ subscriptions }) {
    const rows = subscriptions?.data || [];
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
        { title: 'Started', dataIndex: 'starts_at', render: (value) => value || '-' },
        { title: 'Ends', dataIndex: 'ends_at', render: (value) => value || '-' },
    ];

    return (
        <AuthenticatedLayout header={<PageHeader title="Subscriptions" subtitle="Review tenant billing state and lifecycle dates." />}>
            <Head title="Subscriptions" />
            <DataTable columns={columns} dataSource={rows} />
        </AuthenticatedLayout>
    );
}
