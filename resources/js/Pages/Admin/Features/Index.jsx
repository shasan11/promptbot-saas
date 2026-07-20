import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ features }) {
    const rows = features?.data || [];
    const columns = [
        {
            title: 'Feature',
            dataIndex: 'name',
            render: (value, feature) => (
                <div>
                    <Link href={route('superadmin.features.show', feature.public_uuid || feature.id)} className="font-semibold text-slate-950 hover:text-blue-700">{value}</Link>
                    <div className="mt-1 font-mono text-xs text-slate-500">{feature.code}</div>
                </div>
            ),
        },
        { title: 'Type', dataIndex: 'type', render: (type) => <StatusBadge status={type} /> },
        { title: 'Description', dataIndex: 'description', render: (value) => value || '-' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Features"
                    subtitle="Define the capabilities that plans can enable or limit."
                    actions={<Link href={route('superadmin.features.create')} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Create feature</Link>}
                />
            }
        >
            <Head title="Features" />
            <DataTable columns={columns} dataSource={rows} />
        </AuthenticatedLayout>
    );
}
