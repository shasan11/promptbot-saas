import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function OperationShow({ operation }) {
    return (
        <AuthenticatedLayout header={<PageHeader title={operation.type} subtitle={`Operation ${operation.id}`} />}>
            <Head title="Operation" />
            <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
                <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div className="h-full bg-emerald-600" style={{ width: `${operation.progress || 0}%` }} />
                    </div>
                    <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                        {['status', 'tenant_id', 'reason', 'started_at', 'completed_at', 'failure_message'].map((key) => (
                            <div key={key}>
                                <dt className="text-xs font-bold uppercase text-slate-500">{key.replaceAll('_', ' ')}</dt>
                                <dd className="mt-1 text-sm font-semibold text-slate-900">{operation[key] || '-'}</dd>
                            </div>
                        ))}
                    </dl>
                </section>
                <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-base font-bold text-slate-950">Sanitized Logs</h2>
                    <div className="mt-4 space-y-3">
                        {(operation.logs || []).map((log, index) => (
                            <div key={index} className="rounded-md bg-slate-50 p-3 text-sm text-slate-700">
                                <div className="text-xs font-semibold text-slate-500">{log.at}</div>
                                {log.message}
                            </div>
                        ))}
                        {!(operation.logs || []).length && <div className="text-sm text-slate-500">No logs yet.</div>}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
