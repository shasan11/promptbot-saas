import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import FilterBar from '@/Components/UI/FilterBar';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const provisioningStatus = tenant => tenant.last_provisioning_error ? 'failed' : tenant.provisioned_at ? 'completed' : tenant.provisioning_step && tenant.provisioning_step !== 'pending' ? 'running' : 'pending';

export default function Provisioning({ tenants, filters = {}, stats = {} }) {
    const { auth } = usePage().props;
    const canRetry = auth?.permissions?.includes('tenants.update');
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const apply = next => router.get(route('superadmin.operations.provisioning'), { search, status, ...next }, { preserveState: true, replace: true });
    const retry = tenant => {
        const reason = window.prompt(`Reason to retry provisioning for ${tenant.company_name}:`);
        if (reason?.trim()) router.post(route('superadmin.tenants.retry', tenant.id), { reason: reason.trim() });
    };
    const columns = [
        { title: 'Service', dataIndex: 'company_name', render: (value, tenant) => <div><Link className="font-semibold text-brand-700" href={route('superadmin.tenants.show', tenant.public_uuid || tenant.id)}>{value}</Link><p className="text-xs text-slate-500">{tenant.customer_account?.name || 'Unassigned'}</p></div> },
        { title: 'Status', dataIndex: 'id', render: (_, tenant) => <StatusBadge status={provisioningStatus(tenant)} /> },
        { title: 'Current step', dataIndex: 'provisioning_step', render: value => value || 'Pending' },
        { title: 'Plan', dataIndex: ['plan', 'name'], render: value => value || '—' },
        { title: 'Updated', dataIndex: 'updated_at', render: value => new Date(value).toLocaleString() },
        { title: 'Latest log / error', dataIndex: 'id', render: (_, tenant) => <span className={`block max-w-md truncate text-xs ${tenant.last_provisioning_error ? 'text-rose-700' : 'text-slate-500'}`} title={tenant.last_provisioning_error || tenant.provisioning_logs?.[0]?.message}>{tenant.last_provisioning_error || tenant.provisioning_logs?.[0]?.message || 'No log message'}</span> },
        { title: '', dataIndex: 'id', render: (_, tenant) => <div className="flex gap-2"><Button size="sm" variant="secondary" href={route('superadmin.tenants.show', tenant.public_uuid || tenant.id)}>View logs</Button>{canRetry && provisioningStatus(tenant) === 'failed' && <Button size="sm" variant="danger" onClick={() => retry(tenant)}>Retry</Button>}</div> },
    ];
    return <AuthenticatedLayout header={<PageHeader title="Tenant provisioning" subtitle="Live central provisioning state, sanitized failure details, and operational recovery." />}><Head title="Tenant provisioning" />
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><StatCard title="Pending" value={stats.pending || 0} tone="amber" /><StatCard title="Running" value={stats.running || 0} tone="indigo" /><StatCard title="Completed" value={stats.completed || 0} tone="emerald" /><StatCard title="Failed" value={stats.failed || 0} tone="rose" /></div>
        <div className="my-5 rounded-lg border border-slate-200 bg-white p-4"><FilterBar><SearchInput value={search} onChange={setSearch} placeholder="Service, slug, or account" className="w-full max-w-xs" /><Select value={status} onChange={event => { setStatus(event.target.value); apply({ status: event.target.value }); }}><option value="">All states</option><option value="pending">Pending</option><option value="running">Running</option><option value="completed">Completed</option><option value="failed">Failed</option></Select><Button size="sm" variant="secondary" onClick={() => apply({})}>Apply</Button></FilterBar></div>
        <DataTable columns={columns} dataSource={tenants.data || []} rowKey="id" /><Pagination links={tenants.links} />
    </AuthenticatedLayout>;
}
