import DangerActionModal from '@/Components/Superadmin/DangerActionModal';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function InfoCard({ label, value, children }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</div>
            <div className="mt-2 text-sm font-semibold text-slate-950">{children || value || '-'}</div>
        </div>
    );
}

function Panel({ title, subtitle, children }) {
    return (
        <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-5">
                <h2 className="text-base font-bold text-slate-950">{title}</h2>
                {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
            </div>
            {children}
        </section>
    );
}

function EmptyState({ children }) {
    return <div className="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">{children}</div>;
}

// Protocol-relative construction: carries over the current page's scheme and
// port so this works unmodified for local dev (http + custom port) and
// production (https, default ports) without hardcoding either.
function tenantUrl(domain) {
    if (typeof window === 'undefined') return `//${domain}`;

    const port = window.location.port ? `:${window.location.port}` : '';

    return `${window.location.protocol}//${domain}${port}`;
}

export default function Show({ tenant }) {
    const routeKey = tenant.public_uuid || tenant.id;
    const [tab, setTab] = useState('overview');
    const [dangerAction, setDangerAction] = useState(null);
    const tabs = ['overview', 'subscription', 'usage', 'features', 'database', 'domains', 'health', 'audit', 'danger'];
    const primaryDomain = (tenant.domains || []).find((domain) => domain.is_primary) || (tenant.domains || [])[0];

    const action = (name, payload = {}) => {
        router.post(route(`superadmin.tenants.${name}`, routeKey), payload, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={tenant.company_name}
                    subtitle="Tenant operations, subscription state, domains, database metadata, health, and danger-zone actions."
                    actions={
                        <div className="flex gap-2">
                            {primaryDomain && (
                                <a href={tenantUrl(primaryDomain.domain)} target="_blank" rel="noopener noreferrer" className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Visit tenant &rarr;</a>
                            )}
                            <Link href={route('superadmin.tenants.edit', routeKey)} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Edit tenant</Link>
                            <Link href={route('superadmin.tenants.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back</Link>
                        </div>
                    }
                />
            }
        >
            <Head title={tenant.company_name} />

            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <InfoCard label="Status"><StatusBadge status={tenant.status} /></InfoCard>
                    <InfoCard label="Slug" value={tenant.slug} />
                    <InfoCard label="Plan" value={tenant.plan?.name} />
                    <InfoCard label="Database" value={tenant.tenancy_db_name} />
                </div>

                <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                    <div className="flex min-w-max gap-1">
                        {tabs.map((item) => (
                            <button
                                key={item}
                                type="button"
                                onClick={() => setTab(item)}
                                className={`rounded-md px-3 py-2 text-sm font-semibold capitalize transition ${tab === item ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100'}`}
                            >
                                {item}
                            </button>
                        ))}
                    </div>
                </div>

                {tab === 'overview' && (
                    <Panel title="Overview" subtitle="Core identity and provisioning status.">
                        <div className="grid gap-4 md:grid-cols-3">
                            <InfoCard label="Tenant ID" value={tenant.id} />
                            <InfoCard label="Public UUID" value={tenant.public_uuid} />
                            <InfoCard label="Provisioning step" value={tenant.provisioning_step} />
                            <InfoCard label="Provisioned at" value={tenant.provisioned_at} />
                            <InfoCard label="Suspended at" value={tenant.suspended_at} />
                            <InfoCard label="Created at" value={tenant.created_at} />
                        </div>
                    </Panel>
                )}

                {tab === 'subscription' && (
                    <Panel title="Subscriptions" subtitle="Current and historical tenant subscription records.">
                        {(tenant.subscriptions || []).length ? (
                            <div className="space-y-3">
                                {tenant.subscriptions.map((subscription) => (
                                    <Link
                                        key={subscription.id}
                                        href={route('superadmin.subscriptions.show', subscription.public_uuid || subscription.id)}
                                        className="block rounded-md border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:bg-white"
                                    >
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <div className="font-semibold text-slate-950">{subscription.plan?.name || 'Unknown plan'}</div>
                                                <div className="mt-1 text-xs text-slate-500">{subscription.billing_interval || 'manual'} billing</div>
                                            </div>
                                            <StatusBadge status={subscription.status} />
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : <EmptyState>No subscriptions yet.</EmptyState>}
                    </Panel>
                )}

                {tab === 'usage' && (
                    <Panel title="Usage" subtitle="Per-tenant usage metering (messages, AI tokens, storage, API requests) is not enabled in this build.">
                        <EmptyState>Usage metering is not tracked yet for this platform.</EmptyState>
                    </Panel>
                )}

                {tab === 'features' && (
                    <Panel title="Features" subtitle="Plan-derived capabilities and tenant overrides will appear here.">
                        {tenant.plan?.features?.length ? (
                            <div className="grid gap-3 md:grid-cols-2">
                                {tenant.plan.features.map((feature) => (
                                    <div key={feature.id} className="rounded-md border border-slate-200 bg-slate-50 p-4">
                                        <div className="font-semibold text-slate-950">{feature.name}</div>
                                        <div className="mt-1 font-mono text-xs text-slate-500">{feature.code}</div>
                                    </div>
                                ))}
                            </div>
                        ) : <EmptyState>No plan features attached yet.</EmptyState>}
                    </Panel>
                )}

                {tab === 'database' && (
                    <Panel title="Database" subtitle="Connection metadata only. Secrets stay hidden.">
                        <div className="grid gap-4 md:grid-cols-3">
                            <InfoCard label="Connection" value={tenant.tenancy_db_connection || 'tenant'} />
                            <InfoCard label="Host" value={tenant.tenancy_db_host} />
                            <InfoCard label="Port" value={tenant.tenancy_db_port} />
                            <InfoCard label="Database" value={tenant.tenancy_db_name} />
                            <InfoCard label="Username" value={tenant.tenancy_db_username} />
                            <InfoCard label="Created by app" value={tenant.database_created_by_app ? 'Yes' : 'No'} />
                        </div>
                    </Panel>
                )}

                {tab === 'domains' && (
                    <Panel title="Domains" subtitle="Tenant hostnames routed through Stancl tenancy.">
                        {(tenant.domains || []).length ? (
                            <div className="grid gap-3 md:grid-cols-2">
                                {tenant.domains.map((domain) => (
                                    <div key={domain.id} className="flex items-center justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 p-4">
                                        <div>
                                            <div className="font-semibold text-slate-950">{domain.domain}</div>
                                            <div className="mt-1 flex items-center gap-2 text-xs text-slate-500">
                                                {domain.is_primary && <span className="rounded-full bg-slate-200 px-2 py-0.5 font-semibold text-slate-700">Primary</span>}
                                                {domain.type}
                                            </div>
                                        </div>
                                        <a href={tenantUrl(domain.domain)} target="_blank" rel="noopener noreferrer" className="whitespace-nowrap rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100">
                                            Visit &rarr;
                                        </a>
                                    </div>
                                ))}
                            </div>
                        ) : <EmptyState>No domains attached.</EmptyState>}
                    </Panel>
                )}

                {tab === 'health' && (
                    <Panel title="Health" subtitle="Central-only health checks for this tenant record.">
                        <div className="grid gap-4 md:grid-cols-3">
                            <InfoCard label="Provisioning"><StatusBadge status={tenant.last_provisioning_error ? 'failed' : 'active'} /></InfoCard>
                            <InfoCard label="Database configured"><StatusBadge status={tenant.tenancy_db_name ? 'active' : 'pending'} /></InfoCard>
                            <InfoCard label="Domain configured"><StatusBadge status={(tenant.domains || []).length ? 'active' : 'pending'} /></InfoCard>
                        </div>
                        {tenant.last_provisioning_error && (
                            <div className="mt-4 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-700">
                                {tenant.last_provisioning_error}
                            </div>
                        )}
                    </Panel>
                )}

                {tab === 'audit' && (
                    <Panel title="Provisioning logs" subtitle="Latest provisioning and operation events.">
                        <div className="space-y-3">
                            {(tenant.provisioning_logs || []).length ? tenant.provisioning_logs.map((log) => (
                                <div key={log.id} className="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm">
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <span className="font-mono font-semibold text-slate-950">{log.step}</span>
                                        <StatusBadge status={log.status} />
                                    </div>
                                    {log.message && <p className="mt-2 text-slate-600">{log.message}</p>}
                                </div>
                            )) : <EmptyState>No provisioning logs yet.</EmptyState>}
                        </div>
                    </Panel>
                )}

                {tab === 'danger' && (
                    <Panel title="Danger Zone" subtitle="High-impact actions for this tenant. Use these carefully.">
                        <div className="flex flex-wrap gap-2">
                            <button type="button" onClick={() => action('retry')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Retry provisioning</button>
                            <button type="button" onClick={() => action('migrate')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Run migrations</button>
                            <button type="button" onClick={() => action('seed')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Run seeders</button>
                            <button type="button" onClick={() => setDangerAction('suspend')} className="rounded-md bg-rose-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-rose-700">Suspend tenant</button>
                            <button type="button" onClick={() => setDangerAction('activate')} className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">Reactivate tenant</button>
                            <button type="button" onClick={() => setDangerAction('delete')} className="rounded-md border border-rose-300 bg-white px-4 py-2 text-sm font-bold text-rose-700 shadow-sm hover:bg-rose-50">Delete tenant</button>
                        </div>
                    </Panel>
                )}
            </div>

            <DangerActionModal
                open={Boolean(dangerAction) && dangerAction !== 'delete'}
                title={`${dangerAction === 'activate' ? 'Reactivate' : 'Suspend'} ${tenant.company_name}`}
                confirmation={tenant.slug}
                processing={false}
                onCancel={() => setDangerAction(null)}
                onConfirm={() => {
                    action(dangerAction, { reason: `${dangerAction} requested from superadmin danger zone` });
                    setDangerAction(null);
                }}
            />

            <DangerActionModal
                open={dangerAction === 'delete'}
                title={`Permanently delete ${tenant.company_name}`}
                confirmation={tenant.slug}
                processing={false}
                onCancel={() => setDangerAction(null)}
                onConfirm={() => {
                    router.delete(route('superadmin.tenants.destroy', routeKey));
                    setDangerAction(null);
                }}
            />
        </AuthenticatedLayout>
    );
}
