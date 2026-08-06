import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const inputClass = 'rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950';

export default function Index({ tickets, tenants = [], administrators = [], filters = {}, stats = {} }) {
    const { auth } = usePage().props;
    const canManage = auth?.permissions?.includes('support.manage');
    const { data, setData } = useForm({
        search: filters.search || '',
        status: filters.status || '',
        priority: filters.priority || '',
        tenant_id: filters.tenant_id || '',
        assigned_to: filters.assigned_to || '',
    });

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('superadmin.tickets.index'), data, { preserveState: true, preserveScroll: true });
    };

    const columns = [
        {
            title: 'Ticket',
            dataIndex: 'number',
            render: (value, ticket) => (
                <div>
                    <Link href={route('superadmin.tickets.show', ticket.id)} className="font-mono text-sm font-semibold text-slate-950 hover:text-blue-700">{value}</Link>
                    <div className="mt-1 max-w-xs truncate text-xs text-slate-500">{ticket.subject}</div>
                </div>
            ),
        },
        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '-' },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Priority', dataIndex: 'priority', render: (value) => <StatusBadge status={value} /> },
        { title: 'Assigned', dataIndex: ['assignee', 'name'], render: (value) => value || 'Unassigned' },
        {
            title: 'SLA due',
            dataIndex: 'sla_due_at',
            render: (value, ticket) => {
                if (!value) return '-';
                const overdue = ['open', 'pending'].includes(ticket.status) && new Date(value) < new Date();
                return <span className={overdue ? 'font-semibold text-rose-600' : ''}>{new Date(value).toLocaleString()}</span>;
            },
        },
        { title: 'Activity', dataIndex: 'last_activity_at', render: (value) => value ? new Date(value).toLocaleString() : '-' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Tickets"
                    subtitle="Manage tenant support requests, SLA deadlines, replies, and internal notes."
                    actions={canManage ? <Link href={route('superadmin.tickets.create')} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Create ticket</Link> : null}
                />
            }
        >
            <Head title="Tickets" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Open" value={stats.open ?? 0} tone="blue" />
                <StatCard title="Pending" value={stats.pending ?? 0} tone="amber" />
                <StatCard title="Urgent" value={stats.urgent ?? 0} tone="rose" />
                <StatCard title="Overdue SLA" value={stats.overdue ?? 0} tone="slate" />
            </div>

            <form onSubmit={applyFilters} className="my-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm xl:grid-cols-[1fr_150px_150px_200px_200px_auto]">
                <input className={inputClass} placeholder="Ticket, subject, requester, tenant" value={data.search} onChange={(event) => setData('search', event.target.value)} />
                <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                    <option value="">All statuses</option>
                    {['open', 'pending', 'resolved', 'closed'].map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
                <select className={inputClass} value={data.priority} onChange={(event) => setData('priority', event.target.value)}>
                    <option value="">All priorities</option>
                    {['low', 'normal', 'high', 'urgent'].map((priority) => <option key={priority} value={priority}>{priority}</option>)}
                </select>
                <select className={inputClass} value={data.tenant_id} onChange={(event) => setData('tenant_id', event.target.value)}>
                    <option value="">All tenants</option>
                    {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                </select>
                <select className={inputClass} value={data.assigned_to} onChange={(event) => setData('assigned_to', event.target.value)}>
                    <option value="">All assignees</option>
                    {administrators.map((admin) => <option key={admin.id} value={admin.id}>{admin.name}</option>)}
                </select>
                <button className="rounded-md bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Filter</button>
            </form>

            <DataTable columns={columns} dataSource={tickets?.data || []} rowKey="id" />
            <Pagination links={tickets?.links} />
        </AuthenticatedLayout>
    );
}
