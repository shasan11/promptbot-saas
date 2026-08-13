import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ stats, series, recentAccounts, recentChurn }) {
    const max = Math.max(1, ...series.flatMap(item => [item.signups, item.trials, item.churn]));
    const accountColumns = [
        { title: 'Account', dataIndex: 'name', render: (value, row) => <Link className="font-semibold text-brand-700" href={route('superadmin.customers.accounts.show', row.id)}>{value}</Link> },
        { title: 'Services', dataIndex: 'tenants_count' },
        { title: 'Status', dataIndex: 'status', render: value => <StatusBadge status={value} /> },
        { title: 'Joined', dataIndex: 'created_at', render: value => new Date(value).toLocaleDateString() },
    ];
    const churnColumns = [
        { title: 'Account', dataIndex: ['customer_account', 'name'], render: value => value || 'Unassigned' },
        { title: 'Service', dataIndex: ['tenant', 'company_name'], render: value => value || '—' },
        { title: 'Plan', dataIndex: ['plan', 'name'], render: value => value || '—' },
        { title: 'Cancelled', dataIndex: 'cancelled_at', render: value => value ? new Date(value).toLocaleDateString() : '—' },
        { title: 'Reason', dataIndex: 'cancellation_reason', render: value => value || '—' },
    ];
    return <AuthenticatedLayout header={<PageHeader title="Growth" subtitle="Account acquisition, trials, conversion, and subscription churn from platform records." />}><Head title="Growth" />
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"><StatCard title="Customer accounts" value={stats.accounts} /><StatCard title="New accounts (30d)" value={stats.newAccounts30d} tone="emerald" /><StatCard title="Active trials" value={stats.activeTrials} tone="amber" /><StatCard title="Churn (30d)" value={stats.churn30d} tone="rose" /><StatCard title="30d conversion" value={`${stats.conversion30d}%`} tone="blue" /></div>
        <div className="mt-6 rounded-lg border border-slate-200 bg-white p-5"><div className="mb-5 flex flex-wrap gap-4 text-xs"><span className="font-semibold text-emerald-700">● Signups</span><span className="font-semibold text-amber-700">● Trials</span><span className="font-semibold text-rose-700">● Churn</span></div><div className="grid min-h-52 grid-cols-12 items-end gap-2">{series.map(item => <div key={item.key} className="flex h-full flex-col justify-end"><div className="flex h-40 items-end justify-center gap-0.5"><div title={`${item.signups} signups`} className="w-2 bg-emerald-500" style={{ height: `${Math.max(item.signups ? 5 : 0, item.signups / max * 100)}%` }} /><div title={`${item.trials} trials`} className="w-2 bg-amber-400" style={{ height: `${Math.max(item.trials ? 5 : 0, item.trials / max * 100)}%` }} /><div title={`${item.churn} churn`} className="w-2 bg-rose-500" style={{ height: `${Math.max(item.churn ? 5 : 0, item.churn / max * 100)}%` }} /></div><p className="mt-2 truncate text-center text-[10px] text-slate-500">{item.label.split(' ')[0]}</p></div>)}</div></div>
        <div className="mt-6 grid gap-6 xl:grid-cols-2"><div><h2 className="mb-3 font-semibold">Recent signups</h2><DataTable columns={accountColumns} dataSource={recentAccounts} rowKey="id" /></div><div><h2 className="mb-3 font-semibold">Recent churn</h2><DataTable columns={churnColumns} dataSource={recentChurn} rowKey="id" /></div></div>
    </AuthenticatedLayout>;
}
