import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';

export default function Index({ checks = [], queue = {}, maintenance = {}, failedJobs = [] }) {
    const { auth } = usePage().props;
    const canMaintain = auth?.permissions?.includes('maintenance.manage');

    const post = (name, params = {}, confirmation = null) => {
        if (confirmation && !window.confirm(confirmation)) return;
        router.post(route(name, params), {}, { preserveScroll: true });
    };

    const columns = [
        { title: 'UUID', dataIndex: 'uuid', render: (value, row) => <span className="font-mono text-xs">{value || row.id}</span> },
        { title: 'Connection', dataIndex: 'connection' },
        { title: 'Queue', dataIndex: 'queue' },
        { title: 'Failed', dataIndex: 'failed_at', render: (value) => value ? new Date(value).toLocaleString() : '-' },
        { title: 'Exception', dataIndex: 'exception', render: (value) => <span title={value} className="block max-w-lg truncate text-xs text-rose-700">{value}</span> },
        {
            title: 'Actions',
            dataIndex: 'id',
            render: (value) => canMaintain ? (
                <div className="flex gap-2">
                    <button type="button" onClick={() => post('superadmin.operations.failed.retry', { failedJob: value })} className="text-sm font-semibold text-blue-700 hover:text-blue-800">Retry</button>
                    <button type="button" onClick={() => post('superadmin.operations.failed.forget', { failedJob: value }, 'Remove this failed job record?')} className="text-sm font-semibold text-rose-600 hover:text-rose-700">Forget</button>
                </div>
            ) : '-',
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="System Health"
                    subtitle="Live readiness checks for the central application, queue, cache, storage, and database."
                    actions={canMaintain ? (
                        <button type="button" onClick={() => post('superadmin.operations.cache.clear', {}, 'Clear all application caches?')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Clear caches
                        </button>
                    ) : null}
                />
            }
        >
            <Head title="System Health" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Queue driver" value={queue.driver || '-'} tone="slate" />
                <StatCard title="Pending jobs" value={queue.pending ?? 0} tone="blue" />
                <StatCard title="Failed jobs" value={queue.failed ?? 0} tone="rose" />
                <StatCard title="Maintenance mode" value={maintenance.enabled ? 'Enabled' : 'Off'} tone={maintenance.enabled ? 'amber' : 'emerald'} />
            </div>

            <section className="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div className="mb-5">
                    <h2 className="text-base font-bold text-slate-950">Readiness checks</h2>
                    <p className="mt-1 text-sm text-slate-500">Last migration: {maintenance.lastMigration || 'Unavailable'}</p>
                </div>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {checks.map((check) => (
                        <div key={check.name} className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="font-semibold text-slate-900">{check.name}</h3>
                                <StatusBadge status={check.status} />
                            </div>
                            <p className="mt-2 break-words text-sm text-slate-600">{check.detail}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="mt-6">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-base font-bold text-slate-950">Failed jobs</h2>
                        <p className="mt-1 text-sm text-slate-500">Retry recoverable jobs or remove stale failure records.</p>
                    </div>
                    {canMaintain && failedJobs.length > 0 && (
                        <div className="flex gap-2">
                            <button type="button" onClick={() => post('superadmin.operations.failed.retry-all', {}, 'Retry every failed job?')} className="rounded-md bg-slate-950 px-3 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Retry all</button>
                            <button type="button" onClick={() => post('superadmin.operations.failed.flush', {}, 'Permanently remove every failed job record?')} className="rounded-md border border-rose-300 bg-white px-3 py-2 text-sm font-bold text-rose-700 shadow-sm hover:bg-rose-50">Flush failed</button>
                        </div>
                    )}
                </div>
                <DataTable columns={columns} dataSource={failedJobs} rowKey="id" />
            </section>
        </AuthenticatedLayout>
    );
}
