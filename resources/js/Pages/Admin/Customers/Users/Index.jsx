import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import { SectionCard } from '@/Components/UI/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ users, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const columns = [
        { title: 'Portal user', dataIndex: 'name', render: (value, user) => <div><p className="font-semibold">{value}</p><p className="text-xs text-slate-500">{user.email}</p></div> },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Accounts', dataIndex: 'accounts_count' },
        { title: 'Memberships', dataIndex: 'accounts', render: (items) => <span className="text-sm text-slate-600">{items.map((account) => account.name).join(', ') || 'None'}</span> },
        { title: 'Last login', dataIndex: 'last_login_at', render: (value) => value ? new Date(value).toLocaleString() : 'Never' },
    ];
    return <AuthenticatedLayout header={<PageHeader title="Portal users" subtitle="Customer identities remain separate from platform administrators and tenant helpdesk users." />}>
        <Head title="Portal users" />
        <SectionCard>
            <form onSubmit={(event) => { event.preventDefault(); router.get(route('superadmin.customers.users.index'), { search }, { preserveState: true }); }} className="mb-4"><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search name or email" className="w-full max-w-md rounded-lg border-slate-300 text-sm" /></form>
            <DataTable rowKey="id" columns={columns} dataSource={users.data} />
            <Pagination links={users.links} />
        </SectionCard>
    </AuthenticatedLayout>;
}
