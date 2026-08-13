import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Avatar from '@/Components/UI/Avatar';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import CopyButton from '@/Components/UI/CopyButton';
import DangerConfirmDialog from '@/Components/UI/DangerConfirmDialog';
import DescriptionList from '@/Components/UI/DescriptionList';
import EmptyState from '@/Components/UI/EmptyState';
import Tabs from '@/Components/UI/Tabs';
import UsageMetrics from '@/Components/UsageMetrics';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, Inbox } from 'lucide-react';
import { useState } from 'react';

function tenantUrl(domain) {
    if (typeof window === 'undefined') return `//${domain}`;
    const port = window.location.port ? `:${window.location.port}` : '';
    return `${window.location.protocol}//${domain}${port}`;
}

export default function Show({ tenant, usage, activities = [] }) {
    const routeKey = tenant.public_uuid || tenant.id;
    const [tab, setTab] = useState('overview');
    const [dangerAction, setDangerAction] = useState(null);
    const primaryDomain = (tenant.domains || []).find((domain) => domain.is_primary) || (tenant.domains || [])[0];

    const action = (name, payload = {}) => {
        router.post(route(`superadmin.tenants.${name}`, routeKey), payload, { preserveScroll: true });
    };

    const tabs = [
        { value: 'overview', label: 'Overview' },
        { value: 'subscription', label: 'Subscription' },
        { value: 'features', label: 'Features' },
        { value: 'billing', label: 'Billing' },
        { value: 'usage', label: 'Usage' },
        { value: 'provisioning', label: 'Provisioning' },
        { value: 'domains', label: 'Domains' },
        { value: 'activity', label: 'Activity' },
        { value: 'support', label: 'Support' },
    ];

    return (
        <AuthenticatedLayout>
            <Head title={tenant.company_name} />

            <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <Avatar name={tenant.company_name} size="lg" />
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-bold tracking-tight text-slate-900">{tenant.company_name}</h1>
                                <StatusBadge status={tenant.status} />
                            </div>
                            <div className="mt-1 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                                <span className="font-mono text-xs">{tenant.slug}</span>
                                <span>{tenant.plan?.name || 'No plan'}</span>
                                {primaryDomain && (
                                    <a href={tenantUrl(primaryDomain.domain)} target="_blank" rel="noopener noreferrer" className="flex items-center gap-1 font-medium text-navy-800 hover:text-brand-700">
                                        {primaryDomain.domain} <ExternalLink className="h-3 w-3" />
                                    </a>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button href={route('superadmin.tenants.edit', routeKey)} variant="secondary">Edit tenant</Button>
                        <Button href={route('superadmin.tenants.index')} variant="ghost">Back</Button>
                    </div>
                </div>
            </div>

            <div className="mt-6">
                <Tabs items={tabs} active={tab} onChange={setTab} />
            </div>

            <div className="mt-6 space-y-6">
                {tab === 'overview' && (
                    <SectionCard title="Overview" description="Core identity and provisioning status.">
                        <DescriptionList
                            columns={3}
                            items={[
                                { label: 'Tenant ID', value: <span className="flex items-center gap-1 font-mono text-xs">{tenant.id}<CopyButton value={tenant.id} /></span> },
                                { label: 'Public UUID', value: <span className="flex items-center gap-1 font-mono text-xs">{tenant.public_uuid}<CopyButton value={tenant.public_uuid} /></span> },
                                { label: 'Provisioning step', value: tenant.provisioning_step },
                                { label: 'Provisioned at', value: tenant.provisioned_at },
                                { label: 'Suspended at', value: tenant.suspended_at },
                                { label: 'Created at', value: tenant.created_at },
                            ]}
                        />
                    </SectionCard>
                )}

                {['subscription', 'features', 'billing', 'usage'].includes(tab) && (
                    <>
                        {tab === 'subscription' && <SectionCard title="Subscriptions" description="Current and historical tenant subscription records.">
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
                                                    <div className="font-semibold text-slate-900">{subscription.plan?.name || 'Unknown plan'}</div>
                                                    <div className="mt-1 text-xs text-slate-500">{subscription.billing_interval || 'manual'} billing</div>
                                                </div>
                                                <StatusBadge status={subscription.status} />
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            ) : <EmptyState icon={Inbox} title="No subscriptions yet" />}
                        </SectionCard>}

                        {tab === 'features' && <SectionCard title="Plan features" description="Capabilities included with the tenant's current plan.">
                            {tenant.plan?.features?.length ? (
                                <div className="grid gap-3 md:grid-cols-2">
                                    {tenant.plan.features.map((feature) => (
                                        <div key={feature.id} className="rounded-md border border-slate-200 bg-slate-50 p-4">
                                            <div className="font-semibold text-slate-900">{feature.name}</div>
                                            <div className="mt-1 font-mono text-xs text-slate-500">{feature.code}</div>
                                        </div>
                                    ))}
                                </div>
                            ) : <EmptyState icon={Inbox} title="No plan features attached" />}
                        </SectionCard>}

                        {tab === 'usage' && <SectionCard title="Usage" description="Real counters from this tenant database compared with the attached plan limits.">
                            <UsageMetrics usage={usage} />
                        </SectionCard>}

                        {tab === 'billing' && <><SectionCard title="Invoices">{(tenant.invoices || []).length ? <div className="divide-y">{tenant.invoices.map(invoice => <Link key={invoice.id} href={route('superadmin.billing.invoices.show', invoice.id)} className="grid gap-3 py-3 text-sm sm:grid-cols-[1fr_auto_auto]"><span className="font-mono font-semibold">{invoice.number}</span><span>{invoice.currency} {Number(invoice.total).toFixed(2)}</span><StatusBadge status={invoice.status} /></Link>)}</div> : <EmptyState icon={Inbox} title="No invoices" />}</SectionCard><SectionCard title="Payments">{(tenant.payments || []).length ? <div className="divide-y">{tenant.payments.map(payment => <Link key={payment.id} href={route('superadmin.billing.payments.show', payment.id)} className="grid gap-3 py-3 text-sm sm:grid-cols-[1fr_auto_auto]"><span className="font-mono font-semibold">{payment.provider_reference || payment.id.slice(0, 8)}</span><span>{payment.currency} {Number(payment.amount).toFixed(2)}</span><StatusBadge status={payment.status} /></Link>)}</div> : <EmptyState icon={Inbox} title="No payments" />}</SectionCard></>}
                    </>
                )}

                {['provisioning', 'domains'].includes(tab) && (
                    <>
                        {tab === 'provisioning' && <SectionCard title="Database" description="Connection metadata only. Secrets stay hidden.">
                            <DescriptionList
                                columns={3}
                                items={[
                                    { label: 'Connection', value: tenant.tenancy_db_connection || 'tenant' },
                                    { label: 'Host', value: tenant.tenancy_db_host },
                                    { label: 'Port', value: tenant.tenancy_db_port },
                                    { label: 'Database', value: tenant.tenancy_db_name },
                                    { label: 'Username', value: tenant.tenancy_db_username },
                                    { label: 'Created by app', value: tenant.database_created_by_app ? 'Yes' : 'No' },
                                ]}
                            />
                        </SectionCard>}

                        {tab === 'domains' && <SectionCard title="Domains" description="Tenant hostnames routed through Stancl tenancy.">
                            {(tenant.domains || []).length ? (
                                <div className="grid gap-3 md:grid-cols-2">
                                    {tenant.domains.map((domain) => (
                                        <div key={domain.id} className="flex items-center justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 p-4">
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="truncate font-semibold text-slate-900">{domain.domain}</span>
                                                    <CopyButton value={domain.domain} />
                                                </div>
                                                <div className="mt-1 flex items-center gap-2 text-xs text-slate-500">
                                                    {domain.is_primary && <span className="rounded-full bg-slate-200 px-2 py-0.5 font-semibold text-slate-700">Primary</span>}
                                                    {domain.type}
                                                </div>
                                            </div>
                                            <a href={tenantUrl(domain.domain)} target="_blank" rel="noopener noreferrer" className="whitespace-nowrap rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-soft hover:bg-slate-100">
                                                Visit →
                                            </a>
                                        </div>
                                    ))}
                                </div>
                            ) : <EmptyState icon={Inbox} title="No domains attached" />}
                        </SectionCard>}

                        {tab === 'provisioning' && <SectionCard title="Health" description="Central-only health checks for this tenant record.">
                            <DescriptionList
                                columns={3}
                                items={[
                                    { label: 'Provisioning', value: <StatusBadge status={tenant.last_provisioning_error ? 'failed' : 'active'} /> },
                                    { label: 'Database configured', value: <StatusBadge status={tenant.tenancy_db_name ? 'active' : 'pending'} /> },
                                    { label: 'Domain configured', value: <StatusBadge status={(tenant.domains || []).length ? 'active' : 'pending'} /> },
                                ]}
                            />
                            {tenant.last_provisioning_error && (
                                <p className="mt-4 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-700">{tenant.last_provisioning_error}</p>
                            )}
                        </SectionCard>}
                    </>
                )}

                {tab === 'provisioning' && (
                    <SectionCard title="Provisioning logs" description="Latest provisioning and operation events.">
                        <div className="space-y-3">
                            {(tenant.provisioning_logs || []).length ? tenant.provisioning_logs.map((log) => (
                                <div key={log.id} className="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm">
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <span className="font-mono font-semibold text-slate-900">{log.step}</span>
                                        <StatusBadge status={log.status} />
                                    </div>
                                    {log.message && <p className="mt-2 text-slate-600">{log.message}</p>}
                                </div>
                            )) : <EmptyState icon={Inbox} title="No provisioning logs yet" />}
                        </div>
                    </SectionCard>
                )}
                {tab === 'activity' && <SectionCard title="Service activity">{activities.length ? <div className="space-y-3">{activities.map(item => <div key={item.id} className="border-l-2 border-indigo-200 pl-3"><p className="text-sm font-medium">{item.description || item.event}</p><p className="text-xs text-slate-500">{new Date(item.created_at).toLocaleString()}</p></div>)}</div> : <EmptyState icon={Inbox} title="No service activity" />}</SectionCard>}
                {tab === 'support' && <SectionCard title="Support cases">{(tenant.support_tickets || []).length ? <div className="divide-y">{tenant.support_tickets.map(ticket => <Link key={ticket.id} href={route('superadmin.tickets.show', ticket.public_uuid || ticket.id)} className="grid gap-3 py-3 text-sm sm:grid-cols-[auto_1fr_auto]"><span className="font-mono">{ticket.number}</span><span className="font-semibold">{ticket.subject}</span><StatusBadge status={ticket.status} /></Link>)}</div> : <EmptyState icon={Inbox} title="No support cases" />}</SectionCard>}
            </div>

            <section className="mt-8 rounded-lg border border-rose-200 bg-rose-50/40 shadow-soft">
                <div className="border-b border-rose-100 px-5 py-4">
                    <h2 className="text-sm font-semibold text-rose-800">Danger zone</h2>
                    <p className="mt-0.5 text-xs text-rose-700/80">Infrastructure and access-affecting operations for this tenant.</p>
                </div>
                <div className="flex flex-wrap gap-2 p-5">
                    <Button variant="secondary" onClick={() => setDangerAction('retry')}>Retry provisioning</Button>
                    <Button variant="secondary" onClick={() => setDangerAction('migrate')}>Run migrations</Button>
                    <Button variant="secondary" onClick={() => setDangerAction('seed')}>Run seeders</Button>
                    {tenant.status === 'suspended' ? (
                        <Button variant="brand" onClick={() => setDangerAction('activate')}>Reactivate tenant</Button>
                    ) : (
                        <Button variant="danger" onClick={() => setDangerAction('suspend')}>Suspend tenant</Button>
                    )}
                    <Button variant="danger" onClick={() => setDangerAction('delete')}>Delete tenant</Button>
                </div>
            </section>

            <DangerConfirmDialog
                open={dangerAction === 'suspend'}
                title={`Suspend ${tenant.company_name}`}
                consequence="Tenant users will immediately lose access to their workspace and API."
                affected={primaryDomain?.domain}
                reversible
                reasonRequired
                confirmLabel="Suspend tenant"
                onCancel={() => setDangerAction(null)}
                onConfirm={(reason) => { action('suspend', { reason }); setDangerAction(null); }}
            />

            <DangerConfirmDialog
                open={dangerAction === 'activate'}
                title={`Reactivate ${tenant.company_name}`}
                consequence="Tenant users will regain access to their workspace immediately."
                affected={primaryDomain?.domain}
                reversible
                reasonRequired
                confirmLabel="Reactivate tenant"
                onCancel={() => setDangerAction(null)}
                onConfirm={(reason) => { action('activate', { reason }); setDangerAction(null); }}
            />

            {['retry', 'migrate', 'seed'].map((operation) => <DangerConfirmDialog
                key={operation}
                open={dangerAction === operation}
                title={`${operation === 'retry' ? 'Retry provisioning for' : operation === 'migrate' ? 'Run migrations for' : 'Run seeders for'} ${tenant.company_name}`}
                consequence={operation === 'retry' ? 'Provisioning will resume against the existing workspace.' : operation === 'migrate' ? 'Pending tenant database migrations will execute immediately.' : 'The tenant database seeder will execute immediately.'}
                affected={primaryDomain?.domain || tenant.slug}
                reversible={operation === 'retry'}
                reasonRequired
                confirmLabel={operation === 'retry' ? 'Retry provisioning' : operation === 'migrate' ? 'Run migrations' : 'Run seeders'}
                onCancel={() => setDangerAction(null)}
                onConfirm={(reason) => { action(operation, { reason }); setDangerAction(null); }}
            />)}

            <DangerConfirmDialog
                open={dangerAction === 'delete'}
                title={`Permanently delete ${tenant.company_name}`}
                consequence="This removes the tenant record, its database connection metadata, and all associated billing history from the platform."
                affected={tenant.slug}
                confirmation={tenant.slug}
                reasonRequired
                confirmLabel="Delete tenant"
                onCancel={() => setDangerAction(null)}
                onConfirm={(reason) => { router.delete(route('superadmin.tenants.destroy', routeKey), { data: { reason } }); setDangerAction(null); }}
            />
        </AuthenticatedLayout>
    );
}
