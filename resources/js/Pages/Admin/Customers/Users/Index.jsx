import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import { SectionCard } from '@/Components/UI/Card';
import Button from '@/Components/UI/Button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus, UserPlus } from 'lucide-react';
import { useState } from 'react';

export default function Index({ users, filters, accounts = [] }) {
    const [search, setSearch] = useState(filters.search || '');
    const [creating, setCreating] = useState(false);
    const form = useForm({ name: '', email: '', password: '', status: 'active', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone, account_id: '', role: 'member' });
    const columns = [
        { title: 'Portal user', dataIndex: 'name', render: (value, user) => <div><p className="font-semibold">{value}</p><p className="text-xs text-slate-500">{user.email}</p></div> },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Accounts', dataIndex: 'accounts_count' },
        { title: 'Memberships', dataIndex: 'accounts', render: (items) => <span className="text-sm text-slate-600">{items.map((account) => account.name).join(', ') || 'None'}</span> },
        { title: 'Last login', dataIndex: 'last_login_at', render: (value) => value ? new Date(value).toLocaleString() : 'Never' },
    ];
    return <AuthenticatedLayout header={<PageHeader title="Portal users" subtitle="Create and manage the customer identities that access accounts and services." actions={<Button variant="brand" icon={Plus} onClick={() => setCreating(value => !value)}>{creating ? 'Close form' : 'Create portal user'}</Button>} />}>
        <Head title="Portal users" />
        {creating && <SectionCard title="Create portal user" description="Set a password now, or leave it empty to email a secure password-setup link." className="mb-5">
            <form onSubmit={event => { event.preventDefault(); form.post(route('superadmin.customers.users.store'), { preserveScroll: true, onSuccess: () => { form.reset(); setCreating(false); } }); }} className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {[['name','Full name','text'],['email','Email address','email'],['password','Temporary password (optional)','password'],['timezone','Timezone','text']].map(([key,label,type]) => <label key={key} className="text-sm font-medium text-slate-700">{label}<input type={type} value={form.data[key]} onChange={event => form.setData(key,event.target.value)} required={['name','email'].includes(key)} className="mt-1.5 w-full rounded-lg border-slate-300 text-sm" />{form.errors[key] && <span className="mt-1 block text-xs text-rose-600">{form.errors[key]}</span>}</label>)}
                <label className="text-sm font-medium text-slate-700">Status<select value={form.data.status} onChange={event => form.setData('status',event.target.value)} className="mt-1.5 w-full rounded-lg border-slate-300 text-sm"><option value="active">Active</option><option value="invited">Invited</option><option value="suspended">Suspended</option></select></label>
                <label className="text-sm font-medium text-slate-700">Attach to account<select value={form.data.account_id} onChange={event => form.setData('account_id',event.target.value)} className="mt-1.5 w-full rounded-lg border-slate-300 text-sm"><option value="">No account yet</option>{accounts.map(account => <option key={account.id} value={account.id}>{account.name} · {account.account_number}</option>)}</select></label>
                {form.data.account_id && <label className="text-sm font-medium text-slate-700">Account role<select value={form.data.role} onChange={event => form.setData('role',event.target.value)} className="mt-1.5 w-full rounded-lg border-slate-300 text-sm"><option value="member">Member</option><option value="billing">Billing</option><option value="admin">Account admin</option></select></label>}
                <div className="flex items-end md:col-span-2 xl:col-span-3"><Button type="submit" variant="brand" icon={UserPlus} loading={form.processing}>Create portal user</Button></div>
            </form>
        </SectionCard>}
        <SectionCard>
            <form onSubmit={(event) => { event.preventDefault(); router.get(route('superadmin.customers.users.index'), { search }, { preserveState: true }); }} className="mb-4"><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search name or email" className="w-full max-w-md rounded-lg border-slate-300 text-sm" /></form>
            <DataTable rowKey="id" columns={columns} dataSource={users.data} />
            <Pagination links={users.links} />
        </SectionCard>
    </AuthenticatedLayout>;
}
