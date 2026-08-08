import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import FilterBar from '@/Components/UI/FilterBar';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Headphones, Plus } from 'lucide-react';
import { useState } from 'react';

export default function Index({ tickets, tenants = [], administrators = [], filters = {}, stats = {} }) {
    const { auth } = usePage().props;
    const canManage = auth?.permissions?.includes('support.manage');
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [priority, setPriority] = useState(filters.priority || '');
    const [tenantId, setTenantId] = useState(filters.tenant_id || '');
    const [assignedTo, setAssignedTo] = useState(filters.assigned_to || '');

    const applyFilters = (next = {}) => {
        router.get(route('superadmin.tickets.index'), { search, status, priority, tenant_id: tenantId, assigned_to: assignedTo, ...next }, { preserveState: true, preserveScroll: true });
    };

    const columns = [
        {
            title: 'Ticket',
            dataIndex: 'number',
            render: (value, ticket) => (
                <div>
                    <Link href={route('superadmin.tickets.show', ticket.id)} className="font-mono text-sm font-semibold text-slate-900 hover:text-brand-700">{value}</Link>
                    <div className="mt-1 max-w-xs truncate text-xs text-slate-500">{ticket.subject}</div>
                </div>
            ),
        },
        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '—' },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Priority', dataIndex: 'priority', render: (value) => <StatusBadge status={value} /> },
        {
            title: 'Assigned',
            dataIndex: ['assignee', 'name'],
            render: (value) => value ? <span className="flex items-center gap-2"><Avatar name={value} size="sm" />{value}</span> : <span className="text-slate-400">Unassigned</span>,
        },
        {
            title: 'SLA due',
            dataIndex: 'sla_due_at',
            render: (value, ticket) => {
                if (!value) return '—';
                const overdue = ['open', 'pending'].includes(ticket.status) && new Date(value) < new Date();
                return overdue ? <Badge tone="danger">Overdue · {new Date(value).toLocaleDateString()}</Badge> : new Date(value).toLocaleString();
            },
        },
        { title: 'Activity', dataIndex: 'last_activity_at', render: (value) => value ? new Date(value).toLocaleString() : '—' },
    ];

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="Support tickets"
                    subtitle="Manage tenant support requests, SLA deadlines, replies, and internal notes."
                    actions={canManage && <Button href={route('superadmin.tickets.create')} variant="brand" icon={Plus}>Create ticket</Button>}
                />
            )}
        >
            <Head title="Tickets" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Open" value={stats.open ?? 0} tone="blue" />
                <StatCard title="Pending" value={stats.pending ?? 0} tone="amber" />
                <StatCard title="Urgent" value={stats.urgent ?? 0} tone="rose" />
                <StatCard title="Overdue SLA" value={stats.overdue ?? 0} tone={stats.overdue ? 'rose' : 'slate'} />
            </div>

            <div className="my-5 rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
                <FilterBar>
                    <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); applyFilters({ search: '' }); }} placeholder="Ticket, subject, requester, tenant" className="w-full max-w-xs" />
                    <Select value={status} onChange={(event) => { setStatus(event.target.value); applyFilters({ status: event.target.value }); }} className="w-40">
                        <option value="">All statuses</option>
                        {['open', 'pending', 'resolved', 'closed'].map((item) => <option key={item} value={item}>{item}</option>)}
                    </Select>
                    <Select value={priority} onChange={(event) => { setPriority(event.target.value); applyFilters({ priority: event.target.value }); }} className="w-40">
                        <option value="">All priorities</option>
                        {['low', 'normal', 'high', 'urgent'].map((item) => <option key={item} value={item}>{item}</option>)}
                    </Select>
                    <Select value={tenantId} onChange={(event) => { setTenantId(event.target.value); applyFilters({ tenant_id: event.target.value }); }} className="w-48">
                        <option value="">All tenants</option>
                        {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                    </Select>
                    <Select value={assignedTo} onChange={(event) => { setAssignedTo(event.target.value); applyFilters({ assigned_to: event.target.value }); }} className="w-48">
                        <option value="">All assignees</option>
                        {administrators.map((admin) => <option key={admin.id} value={admin.id}>{admin.name}</option>)}
                    </Select>
                </FilterBar>
            </div>

            {(tickets?.data || []).length ? (
                <>
                    <DataTable columns={columns} dataSource={tickets?.data || []} rowKey="id" />
                    <Pagination links={tickets?.links} />
                </>
            ) : (
                <EmptyState icon={Headphones} title="No tickets found" description="Try a different search term or filter." />
            )}
        </AuthenticatedLayout>
    );
}
