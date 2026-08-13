import Panel from '@/Components/Portal/Panel';
import StatusPill from '@/Components/Portal/StatusPill';
import PortalLayout from '@/Layouts/PortalLayout';
import { Link, router, usePage } from '@inertiajs/react';

function UsageSummary({ usage }) {
    if (!usage?.available) return <span className="text-slate-400">Unavailable</span>;
    const metrics = usage.metrics?.slice(0, 2) || [];
    return metrics.length
        ? <span>{metrics.map((metric) => `${metric.label}: ${Number(metric.used).toLocaleString()}${metric.limit ? `/${Number(metric.limit).toLocaleString()}` : ''}`).join(' · ')}</span>
        : <span className="text-slate-400">No counters</span>;
}

export default function Index({ workspaces, filters }) {
    const membership = usePage().props.portal?.membership;
    const canCreate = (['owner', 'admin'].includes(membership?.role) || !!membership?.can_manage_services)
        && usePage().props.portal?.features?.workspaceCreation !== false;
    const canBilling = ['owner', 'admin', 'billing'].includes(membership?.role) || !!membership?.can_manage_billing;
    const statuses = ['', 'active', 'trial', 'provisioning', 'suspended', 'failed', 'cancelled'];

    return (
        <PortalLayout
            title="Workspaces"
            actions={canCreate && <Link href={route('portal.workspaces.create')} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Create workspace</Link>}
        >
            <div className="mb-4 flex flex-wrap gap-2">
                {statuses.map((status) => (
                    <button key={status || 'all'} onClick={() => router.get(route('portal.workspaces.index'), status ? { status } : {}, { preserveState: true })} className={`rounded-full px-3 py-1.5 text-sm font-medium ${String(filters.status || '') === status ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600'}`}>
                        {status ? status.replaceAll('_', ' ') : 'All'}
                    </button>
                ))}
            </div>
            <Panel>
                {workspaces.data.length ? (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {workspaces.data.map((workspace) => {
                            const subscription = workspace.subscriptions?.[0];
                            const domain = workspace.domains?.find((item) => item.is_primary) || workspace.domains?.[0];
                            return (
                                <article key={workspace.id} className="rounded-xl border border-slate-200 p-5">
                                    <div className="flex items-start justify-between gap-3">
                                        <div><h2 className="font-semibold text-slate-950">{workspace.company_name}</h2><p className="mt-1 text-sm text-slate-500">{domain?.domain || workspace.slug}</p></div>
                                        <StatusPill value={workspace.status} />
                                    </div>
                                    <dl className="mt-5 space-y-2 text-sm">
                                        <div className="flex justify-between"><dt className="text-slate-500">Plan</dt><dd className="font-medium">{subscription?.plan?.name || workspace.plan?.name || 'None'}</dd></div>
                                        <div className="flex justify-between"><dt className="text-slate-500">Subscription</dt><dd><StatusPill value={subscription?.status || 'pending'} /></dd></div>
                                        <div className="flex justify-between"><dt className="text-slate-500">Billing</dt><dd className="capitalize">{subscription?.billing_interval || '—'}</dd></div>
                                        <div className="flex justify-between"><dt className="text-slate-500">Created</dt><dd>{new Date(workspace.created_at).toLocaleDateString()}</dd></div>
                                        <div className="flex justify-between"><dt className="text-slate-500">Renewal</dt><dd>{subscription?.current_period_ends_at ? new Date(subscription.current_period_ends_at).toLocaleDateString() : '—'}</dd></div>
                                    </dl>
                                    <div className="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600"><span className="font-semibold">Usage: </span><UsageSummary usage={workspace.usage_summary} /></div>
                                    <div className={`mt-5 grid gap-2 ${canBilling ? 'grid-cols-3' : 'grid-cols-2'}`}>
                                        <Link href={route('portal.workspaces.show', workspace.public_uuid)} className="rounded-lg bg-slate-900 px-2 py-2 text-center text-xs font-semibold text-white">Manage</Link>
                                        {domain?.domain ? <a href={`//${domain.domain}`} className="rounded-lg border border-slate-300 px-2 py-2 text-center text-xs font-semibold text-slate-700">Open</a> : <span className="rounded-lg border border-slate-200 px-2 py-2 text-center text-xs text-slate-400">No domain</span>}
                                        {canBilling && <Link href={route('portal.billing.subscriptions', { workspace: workspace.id })} className="rounded-lg border border-slate-300 px-2 py-2 text-center text-xs font-semibold text-slate-700">Billing</Link>}
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                ) : (
                    <div className="py-14 text-center">
                        <h2 className="font-semibold text-slate-900">No workspaces yet</h2>
                        <p className="mt-1 text-sm text-slate-500">{canCreate ? 'Create your first PromptBot workspace.' : 'No workspaces are assigned to this account.'}</p>
                        {canCreate && <Link href={route('portal.workspaces.create')} className="mt-5 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Create workspace</Link>}
                    </div>
                )}
            </Panel>
        </PortalLayout>
    );
}
