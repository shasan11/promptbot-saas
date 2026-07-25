import PageHeader from '@/Components/Superadmin/PageHeader';
import StatCard from '@/Components/Superadmin/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Health({ checks = [], queue = {}, operations = [], backups = [] }) {
    return (
        <AuthenticatedLayout header={<PageHeader title="System Health" subtitle="Central platform readiness, queue posture, backups, and recent operations." />}>
            <Head title="System Health" />
            <div className="grid gap-4 md:grid-cols-3">
                <StatCard title="Queue Driver" value={queue.driver || '-'} tone="slate" />
                <StatCard title="Failed Jobs" value={queue.failed_jobs || 0} tone="rose" />
                <StatCard title="Pending Operations" value={queue.pending_operations || 0} tone="amber" />
            </div>
            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                {[['Health Checks', checks], ['Recent Operations', operations], ['Backups', backups]].map(([title, rows]) => (
                    <section key={title} className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-base font-bold text-slate-950">{title}</h2>
                        <div className="mt-4 space-y-3">
                            {rows.length ? rows.map((row) => (
                                <div key={row.id} className="rounded-md bg-slate-50 p-3 text-sm">
                                    <div className="font-semibold text-slate-900">{row.name || row.type || row.scope}</div>
                                    <div className="text-slate-500">{row.status}</div>
                                </div>
                            )) : <div className="text-sm text-slate-500">No records yet.</div>}
                        </div>
                    </section>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
