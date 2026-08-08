import DataTable from '@/Components/Superadmin/DataTable';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle, ArrowRight, Building2, CreditCard, Headphones, Inbox, ReceiptText, Wallet,
} from 'lucide-react';

const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function greeting() {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
}

export default function Dashboard({ currency = 'USD', stats = {}, recentTenants = [], subscriptionsByStatus = [], recentPayments = [], urgentTickets = [] }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0];
    const needsAttention = (stats.outstandingInvoices ?? 0) + urgentTickets.length;

    const tenantColumns = [
        {
            title: 'Tenant',
            dataIndex: 'company_name',
            render: (value, tenant) => (
                <div>
                    <Link className="font-semibold text-slate-900 hover:text-brand-700" href={route('superadmin.tenants.show', tenant.public_uuid || tenant.id)}>{value}</Link>
                    <div className="mt-1 text-xs text-slate-500">{tenant.slug}</div>
                </div>
            ),
        },
        { title: 'Status', dataIndex: 'status', render: (status) => <StatusBadge status={status} /> },
        { title: 'Plan', dataIndex: ['plan', 'name'], render: (value) => value || '—' },
        {
            title: 'Primary domain',
            dataIndex: 'domains',
            render: (domains = []) => domains.find((domain) => domain.is_primary)?.domain || domains[0]?.domain || '—',
        },
    ];

    const paymentColumns = [
        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '—' },
        { title: 'Invoice', dataIndex: ['invoice', 'number'], render: (value) => value || '—' },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Amount', dataIndex: 'amount', render: (value, payment) => `${payment.currency} ${money(value)}` },
    ];

    const ticketColumns = [
        {
            title: 'Ticket',
            dataIndex: 'number',
            render: (value, ticket) => <Link href={route('superadmin.tickets.show', ticket.id)} className="font-mono text-sm font-semibold text-navy-800 hover:text-brand-700">{value}</Link>,
        },
        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '—' },
        { title: 'Priority', dataIndex: 'priority', render: (value) => <StatusBadge status={value} /> },
        { title: 'Assigned', dataIndex: ['assignee', 'name'], render: (value) => value || 'Unassigned' },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Superadmin dashboard" />

            <div className="relative overflow-hidden rounded-lg bg-gradient-to-br from-navy-900 via-navy-800 to-navy-900 px-6 py-8 shadow-soft-lg sm:px-8">
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.07]"
                    style={{ backgroundImage: 'radial-gradient(circle at 1px 1px, white 1px, transparent 0)', backgroundSize: '20px 20px' }}
                    aria-hidden="true"
                />
                <div className="relative flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-brand-300">{greeting()}{firstName ? `, ${firstName}` : ''}</p>
                        <h1 className="mt-1.5 text-2xl font-bold tracking-tight text-white sm:text-3xl">Command center</h1>
                        <p className="mt-1.5 max-w-xl text-sm text-slate-300">Live tenant, billing, and support operations across the platform.</p>
                    </div>
                    <Button href={route('superadmin.tenants.create')} variant="brand" icon={ArrowRight} className="shrink-0">Provision a tenant</Button>
                </div>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Active tenants" value={stats.activeTenants ?? 0} tone="emerald" icon={Building2} />
                <StatCard title="Active subscriptions" value={stats.activeSubscriptions ?? 0} tone="blue" icon={Wallet} />
                <StatCard title={`Net collected (${currency})`} value={money(stats.netCollected)} tone="emerald" icon={CreditCard} />
                <StatCard title="Open tickets" value={stats.openTickets ?? 0} tone={stats.openTickets ? 'amber' : 'slate'} icon={Headphones} />
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-x-8 gap-y-2 rounded-md border border-slate-200 bg-white px-5 py-3 text-sm text-slate-600 shadow-soft">
                <span><span className="font-semibold text-slate-900">{stats.tenants ?? 0}</span> total tenants</span>
                <span><span className="font-semibold text-slate-900">{stats.plans ?? 0}</span> active plans</span>
                <span><span className="font-semibold text-slate-900">{stats.outstandingInvoices ?? 0}</span> outstanding invoices</span>
            </div>

            {needsAttention > 0 && (
                <section className="mt-6 rounded-lg border border-amber-200 bg-amber-50/40 shadow-soft">
                    <div className="border-b border-amber-100 px-5 py-4">
                        <h2 className="flex items-center gap-2 text-sm font-semibold text-amber-800">
                            <AlertTriangle className="h-4 w-4" /> Needs attention
                        </h2>
                        <p className="mt-0.5 text-xs text-amber-700/80">Items with a direct impact on tenant billing or support SLAs.</p>
                    </div>
                    <div className="grid gap-3 p-5 sm:grid-cols-2">
                        {stats.outstandingInvoices > 0 && (
                            <Link href={route('superadmin.billing.invoices.index')} className="group flex items-center justify-between rounded-md border border-amber-200 bg-white px-4 py-3 text-sm shadow-soft transition hover:border-amber-300 hover:shadow-soft-lg">
                                <span className="flex items-center gap-2.5 font-medium text-slate-800">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-md bg-amber-50 text-amber-600"><ReceiptText className="h-4 w-4" /></span>
                                    Outstanding invoices
                                </span>
                                <span className="flex items-center gap-1.5 font-bold text-amber-700">
                                    {stats.outstandingInvoices}
                                    <ArrowRight className="h-3.5 w-3.5 opacity-0 transition group-hover:opacity-100" />
                                </span>
                            </Link>
                        )}
                        {urgentTickets.length > 0 && (
                            <Link href={route('superadmin.tickets.index')} className="group flex items-center justify-between rounded-md border border-amber-200 bg-white px-4 py-3 text-sm shadow-soft transition hover:border-amber-300 hover:shadow-soft-lg">
                                <span className="flex items-center gap-2.5 font-medium text-slate-800">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-md bg-amber-50 text-amber-600"><Headphones className="h-4 w-4" /></span>
                                    Urgent tickets
                                </span>
                                <span className="flex items-center gap-1.5 font-bold text-amber-700">
                                    {urgentTickets.length}
                                    <ArrowRight className="h-3.5 w-3.5 opacity-0 transition group-hover:opacity-100" />
                                </span>
                            </Link>
                        )}
                    </div>
                </section>
            )}

            <div className="mt-6 grid gap-6 xl:grid-cols-[1fr_320px]">
                <SectionCard
                    title="Recent tenants"
                    actions={<Button href={route('superadmin.tenants.index')} variant="ghost" size="sm">View all</Button>}
                >
                    {recentTenants.length ? (
                        <DataTable rowKey="id" columns={tenantColumns} dataSource={recentTenants} />
                    ) : (
                        <EmptyState icon={Building2} title="No tenants yet" description="Provision your first tenant to see activity here." action={<Button href={route('superadmin.tenants.create')} variant="brand" size="sm">Provision tenant</Button>} />
                    )}
                </SectionCard>

                <SectionCard title="Subscription health">
                    <div className="space-y-3">
                        {subscriptionsByStatus.length ? subscriptionsByStatus.map((item) => (
                            <div key={item.status} className="flex items-center justify-between rounded-md bg-slate-50 px-4 py-3">
                                <StatusBadge status={item.status} />
                                <span className="text-lg font-bold text-slate-900">{item.total}</span>
                            </div>
                        )) : <EmptyState icon={Inbox} title="No subscriptions yet" />}
                    </div>
                </SectionCard>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <SectionCard
                    title="Recent payments"
                    actions={<Button href={route('superadmin.billing.payments.index')} variant="ghost" size="sm">View payments</Button>}
                >
                    {recentPayments.length ? (
                        <DataTable rowKey="id" columns={paymentColumns} dataSource={recentPayments} />
                    ) : (
                        <EmptyState icon={CreditCard} title="No payments recorded yet" />
                    )}
                </SectionCard>
                <SectionCard
                    title="Tickets needing attention"
                    actions={<Button href={route('superadmin.tickets.index')} variant="ghost" size="sm">View tickets</Button>}
                >
                    {urgentTickets.length ? (
                        <DataTable rowKey="id" columns={ticketColumns} dataSource={urgentTickets} />
                    ) : (
                        <EmptyState icon={Headphones} title="No urgent tickets" description="Support queue is clear." />
                    )}
                </SectionCard>
            </div>
        </AuthenticatedLayout>
    );
}
