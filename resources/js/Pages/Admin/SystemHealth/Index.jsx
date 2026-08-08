import PageHeader from '@/Components/Superadmin/PageHeader';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import DangerConfirmDialog from '@/Components/UI/DangerConfirmDialog';
import EmptyState from '@/Components/UI/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ChevronDown, PartyPopper, XCircle } from 'lucide-react';
import { useState } from 'react';

const severityIcon = { healthy: CheckCircle2, warning: AlertTriangle, error: XCircle };
const severityTone = {
    healthy: 'border-brand-200 bg-brand-50 text-brand-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    error: 'border-rose-200 bg-rose-50 text-rose-800',
};

function ExceptionRow({ job, canMaintain, onRetry, onForget }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="rounded-md border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <span className="font-mono text-xs text-slate-500">{job.uuid || job.id}</span>
                        <span className="text-xs text-slate-400">{job.connection} · {job.queue}</span>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">Failed {job.failed_at ? new Date(job.failed_at).toLocaleString() : 'at unknown time'}</p>
                    <button type="button" onClick={() => setOpen((value) => !value)} className="mt-2 flex items-center gap-1 text-xs font-semibold text-navy-800 hover:text-brand-700">
                        <ChevronDown className={`h-3 w-3 transition-transform ${open ? 'rotate-180' : ''}`} /> {open ? 'Hide exception' : 'Show exception'}
                    </button>
                    {open && <pre className="mt-2 max-h-48 overflow-auto whitespace-pre-wrap rounded bg-slate-900 p-3 text-xs text-rose-200">{job.exception}</pre>}
                </div>
                {canMaintain && (
                    <div className="flex shrink-0 gap-2">
                        <Button size="sm" variant="secondary" onClick={() => onRetry(job.id)}>Retry</Button>
                        <Button size="sm" variant="ghost" onClick={() => onForget(job.id)}>Forget</Button>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function Index({ checks = [], queue = {}, maintenance = {}, failedJobs = [] }) {
    const { auth } = usePage().props;
    const canMaintain = auth?.permissions?.includes('maintenance.manage');
    const [confirmAction, setConfirmAction] = useState(null);

    const overall = checks.some((check) => check.status === 'error') ? 'error' : checks.some((check) => check.status === 'warning') ? 'warning' : 'healthy';
    const OverallIcon = overall === 'error' ? XCircle : overall === 'warning' ? AlertTriangle : PartyPopper;

    const post = (name, params = {}) => router.post(route(name, params), {}, { preserveScroll: true });

    const runAction = () => {
        const action = confirmAction;
        setConfirmAction(null);
        if (!action) return;
        if (action.type === 'single') post(action.route, action.params);
        else post(action.route);
    };

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="System health"
                    subtitle="Live readiness checks for the central application, queue, cache, storage, and database."
                    actions={canMaintain && <Button variant="secondary" onClick={() => setConfirmAction({ kind: 'clear-caches', type: 'all', route: 'superadmin.operations.cache.clear' })}>Clear caches</Button>}
                />
            )}
        >
            <Head title="System health" />

            <div className={`flex items-center gap-3 rounded-lg border p-4 ${severityTone[overall]}`}>
                <OverallIcon className="h-5 w-5 shrink-0" />
                <div>
                    <p className="text-sm font-semibold">
                        {overall === 'healthy' ? 'All systems healthy' : overall === 'warning' ? 'Some systems need attention' : 'Critical issues detected'}
                    </p>
                    <p className="text-xs opacity-80">Last migration: {maintenance.lastMigration || 'Unavailable'}</p>
                </div>
            </div>

            <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Queue driver" value={queue.driver || '—'} tone="slate" />
                <StatCard title="Pending jobs" value={queue.pending ?? 0} tone="blue" />
                <StatCard title="Failed jobs" value={queue.failed ?? 0} tone={queue.failed ? 'rose' : 'slate'} />
                <StatCard title="Maintenance mode" value={maintenance.enabled ? 'Enabled' : 'Off'} tone={maintenance.enabled ? 'amber' : 'emerald'} />
            </div>

            <SectionCard className="mt-6" title="Readiness checks">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {checks.map((check) => {
                        const Icon = severityIcon[check.status] || CheckCircle2;
                        return (
                            <div key={check.name} className={`rounded-lg border p-4 ${severityTone[check.status] || 'border-slate-200 bg-slate-50 text-slate-700'}`}>
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="flex items-center gap-2 font-semibold"><Icon className="h-4 w-4" />{check.name}</h3>
                                    <StatusBadge status={check.status} />
                                </div>
                                <p className="mt-2 break-words text-sm opacity-90">{check.detail}</p>
                            </div>
                        );
                    })}
                </div>
            </SectionCard>

            <SectionCard
                className="mt-6"
                title="Failed jobs"
                description="Retry recoverable jobs or remove stale failure records."
                actions={canMaintain && failedJobs.length > 0 && (
                    <div className="flex gap-2">
                        <Button size="sm" variant="secondary" onClick={() => setConfirmAction({ kind: 'retry-all', type: 'all', route: 'superadmin.operations.failed.retry-all' })}>Retry all</Button>
                        <Button size="sm" variant="danger" onClick={() => setConfirmAction({ kind: 'flush', type: 'all', route: 'superadmin.operations.failed.flush' })}>Flush failed</Button>
                    </div>
                )}
            >
                {failedJobs.length ? (
                    <div className="space-y-3">
                        {failedJobs.map((job) => (
                            <ExceptionRow
                                key={job.id}
                                job={job}
                                canMaintain={canMaintain}
                                onRetry={(id) => post('superadmin.operations.failed.retry', { failedJob: id })}
                                onForget={(id) => setConfirmAction({ kind: 'forget', type: 'single', route: 'superadmin.operations.failed.forget', params: { failedJob: id } })}
                            />
                        ))}
                    </div>
                ) : (
                    <EmptyState icon={PartyPopper} title="No failed jobs" description="The queue is running cleanly." />
                )}
            </SectionCard>

            <ConfirmDialog
                open={confirmAction?.kind === 'clear-caches'}
                title="Clear application caches?"
                confirmLabel="Clear caches"
                onCancel={() => setConfirmAction(null)}
                onConfirm={runAction}
            >
                This clears the application, route, config, event, and view caches. The next request will be slightly slower while caches rebuild.
            </ConfirmDialog>

            <ConfirmDialog
                open={confirmAction?.kind === 'retry-all'}
                title="Retry every failed job?"
                confirmLabel="Retry all"
                onCancel={() => setConfirmAction(null)}
                onConfirm={runAction}
            >
                All {failedJobs.length} failed job{failedJobs.length === 1 ? '' : 's'} will be re-queued for processing.
            </ConfirmDialog>

            <DangerConfirmDialog
                open={confirmAction?.kind === 'flush'}
                title="Flush all failed jobs"
                consequence={`This permanently removes ${failedJobs.length} failed job record${failedJobs.length === 1 ? '' : 's'}, including their exception details. They cannot be retried afterward.`}
                reversible={false}
                confirmLabel="Flush failed jobs"
                onCancel={() => setConfirmAction(null)}
                onConfirm={runAction}
            />

            <DangerConfirmDialog
                open={confirmAction?.kind === 'forget'}
                title="Remove this failed job record"
                consequence="This permanently deletes the failure record. The job cannot be retried afterward."
                reversible={false}
                confirmLabel="Forget job"
                onCancel={() => setConfirmAction(null)}
                onConfirm={runAction}
            />
        </AuthenticatedLayout>
    );
}
