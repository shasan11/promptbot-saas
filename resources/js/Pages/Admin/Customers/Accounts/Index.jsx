import Money from '@/Components/Portal/Money';
import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import { SectionCard } from '@/Components/UI/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ accounts, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const apply = (next = {}) => router.get(route('superadmin.customers.accounts.index'), { ...filters, search, ...next }, { preserveState: true });
    const columns = [
        { title: 'Account', dataIndex: 'name', render: (value, account) => <div><Link href={route('superadmin.customers.accounts.show', account.public_uuid)} className="font-semibold text-slate-900 hover:text-indigo-600">{value}</Link><p className="text-xs text-slate-500">{account.account_number}</p></div> },
        { title: 'Owner', dataIndex: ['owner', 'name'], render: (value, account) => <div>{value || 'Unassigned'}<p className="text-xs text-slate-500">{account.owner?.email}</p></div> },
        { title: 'Services', dataIndex: 'tenants_count' },
        { title: 'MRR', dataIndex: 'mrr', render: (value, account) => <Money value={value} currency={account.default_currency} /> },
        { title: 'Outstanding', dataIndex: 'outstanding', render: (value, account) => <Money value={value} currency={account.default_currency} /> },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Created', dataIndex: 'created_at', render: (value) => new Date(value).toLocaleDateString() },
    ];
    return <AuthenticatedLayout header={<PageHeader title="Customer accounts" subtitle="The commercial owners of PromptBot services, billing, members, and support." />}>
        <Head title="Customer accounts" />
        <SectionCard>
            <div className="mb-4 flex flex-col gap-3 sm:flex-row">
                <form onSubmit={(event) => { event.preventDefault(); apply(); }} className="flex-1"><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search account, owner, email, number, or workspace" className="w-full rounded-lg border-slate-300 text-sm" /></form>
                <select value={filters.status || ''} onChange={(event) => apply({ status: event.target.value })} className="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option>{['active', 'trial', 'past_due', 'suspended', 'closed'].map((value) => <option key={value} value={value}>{value.replaceAll('_', ' ')}</option>)}</select>
            </div>
            <DataTable rowKey="id" columns={columns} dataSource={accounts.data} />
            {!accounts.data.length && <p className="py-12 text-center text-sm text-slate-500">No customer accounts match these filters.</p>}
            <Pagination links={accounts.links} />
        </SectionCard>
    </AuthenticatedLayout>;
}
