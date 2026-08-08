import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import EmptyState from '@/Components/UI/EmptyState';
import Input from '@/Components/UI/Input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Download, Inbox } from 'lucide-react';

const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const presets = [
    { label: '7 days', days: 7 },
    { label: '30 days', days: 30 },
    { label: 'Quarter', days: 90 },
];

function BarList({ rows, labelKey, valueKey, renderLabel, tone = 'brand' }) {
    const max = Math.max(1, ...rows.map((row) => Number(row[valueKey]) || 0));
    if (!rows.length) return <EmptyState icon={Inbox} title="No data for this range" />;

    return (
        <div className="space-y-3">
            {rows.map((row, index) => {
                const value = Number(row[valueKey]) || 0;
                return (
                    <div key={index}>
                        <div className="mb-1 flex items-center justify-between text-xs">
                            <span className="font-medium text-slate-700">{renderLabel ? renderLabel(row) : row[labelKey]}</span>
                            <span className="font-mono text-slate-500">{value}</span>
                        </div>
                        <div className="h-2 rounded-full bg-slate-100">
                            <div className={`h-2 rounded-full ${tone === 'brand' ? 'bg-brand-500' : 'bg-navy-700'}`} style={{ width: `${Math.max(4, (value / max) * 100)}%` }} />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default function Index({ filters = {}, currency = 'USD', stats = {}, subscriptionStatuses = [], invoiceStatuses = [], paymentProviders = [], ticketStatuses = [], planMix = [], recentPayments = [], recentTickets = [] }) {
    const applyRange = (params) => router.get(route('superadmin.reports.index'), params, { preserveState: true, preserveScroll: true });
    const applyPreset = (days) => applyRange({ from: new Date(Date.now() - days * 86400000).toISOString().slice(0, 10), to: new Date().toISOString().slice(0, 10) });

    const statusColumns = [
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Records', dataIndex: 'total' },
    ];

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="Reports"
                    subtitle="Operational and financial reporting with date filters and CSV exports."
                    actions={(
                        <DropdownMenu
                            trigger={<span className="inline-flex items-center gap-2 rounded-md bg-navy-800 px-4 py-2 text-sm font-semibold text-white shadow-soft hover:bg-navy-900"><Download className="h-4 w-4" /> Export</span>}
                            items={['tenants', 'subscriptions', 'invoices', 'payments', 'tickets'].map((type) => ({
                                label: `Export ${type}`,
                                onClick: () => { window.location.href = route('superadmin.reports.export', { type, ...filters }); },
                            }))}
                        />
                    )}
                />
            )}
        >
            <Head title="Reports" />

            <div className="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
                <div className="flex gap-1.5">
                    {presets.map((preset) => <Button key={preset.days} variant="secondary" size="sm" onClick={() => applyPreset(preset.days)}>{preset.label}</Button>)}
                </div>
                <div className="ml-auto flex flex-wrap items-end gap-3">
                    <label>
                        <span className="block text-xs font-medium text-slate-500">From</span>
                        <Input type="date" className="mt-1" value={filters.from} onChange={(event) => applyRange({ ...filters, from: event.target.value })} />
                    </label>
                    <label>
                        <span className="block text-xs font-medium text-slate-500">To</span>
                        <Input type="date" className="mt-1" value={filters.to} onChange={(event) => applyRange({ ...filters, to: event.target.value })} />
                    </label>
                </div>
            </div>

            <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <StatCard title="New tenants" value={stats.newTenants ?? 0} tone="blue" />
                <StatCard title="Active subscriptions" value={stats.activeSubscriptions ?? 0} tone="slate" />
                <StatCard title={`Invoiced (${currency})`} value={money(stats.invoiced)} tone="amber" />
                <StatCard title={`Collected (${currency})`} value={money(stats.collected)} tone="emerald" />
                <StatCard title={`Refunded (${currency})`} value={money(stats.refunded)} tone="rose" />
                <StatCard title="Open tickets" value={stats.openTickets ?? 0} tone="slate" />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <SectionCard title="Subscription status">
                    <BarList rows={subscriptionStatuses} labelKey="status" valueKey="total" renderLabel={(row) => <StatusBadge status={row.status} />} />
                    <div className="mt-4 border-t border-slate-100 pt-4"><DataTable columns={statusColumns} dataSource={subscriptionStatuses} rowKey="status" /></div>
                </SectionCard>

                <SectionCard title="Ticket status">
                    <BarList rows={ticketStatuses} labelKey="status" valueKey="total" renderLabel={(row) => <StatusBadge status={row.status} />} tone="navy" />
                    <div className="mt-4 border-t border-slate-100 pt-4"><DataTable columns={statusColumns} dataSource={ticketStatuses} rowKey="status" /></div>
                </SectionCard>

                <SectionCard title="Plan mix" description="Active, trialing, and manually managed subscriptions per plan.">
                    <BarList rows={planMix} labelKey="name" valueKey="subscriptions_count" />
                </SectionCard>

                <SectionCard title="Payments by provider">
                    <DataTable
                        columns={[
                            { title: 'Provider', dataIndex: 'provider', render: (value) => value.replaceAll('_', ' ') },
                            { title: 'Currency', dataIndex: 'currency' },
                            { title: 'Records', dataIndex: 'total' },
                            { title: 'Amount', dataIndex: 'amount', render: (value, row) => `${row.currency} ${money(value)}` },
                            { title: 'Refunded', dataIndex: 'refunded', render: (value, row) => `${row.currency} ${money(value)}` },
                        ]}
                        dataSource={paymentProviders}
                        rowKey={(row) => `${row.provider}-${row.currency}`}
                        emptyText="No payments in this range."
                    />
                </SectionCard>

                <SectionCard title="Invoice status by currency" className="xl:col-span-2">
                    <DataTable
                        columns={[
                            ...statusColumns,
                            { title: 'Currency', dataIndex: 'currency' },
                            { title: 'Amount', dataIndex: 'amount', render: (value, row) => `${row.currency} ${money(value)}` },
                        ]}
                        dataSource={invoiceStatuses}
                        rowKey={(row) => `${row.status}-${row.currency}`}
                        emptyText="No invoices in this range."
                    />
                </SectionCard>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <SectionCard title="Recent payments">
                    <DataTable
                        columns={[
                            { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '—' },
                            { title: 'Invoice', dataIndex: ['invoice', 'number'], render: (value) => value || '—' },
                            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
                            { title: 'Amount', dataIndex: 'amount', render: (value, payment) => `${payment.currency} ${money(value)}` },
                        ]}
                        dataSource={recentPayments}
                        rowKey="id"
                        emptyText="No recent payments."
                    />
                </SectionCard>
                <SectionCard title="Recent tickets">
                    <DataTable
                        columns={[
                            { title: 'Number', dataIndex: 'number' },
                            { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '—' },
                            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
                            { title: 'Assigned', dataIndex: ['assignee', 'name'], render: (value) => value || 'Unassigned' },
                        ]}
                        dataSource={recentTickets}
                        rowKey="id"
                        emptyText="No recent tickets."
                    />
                </SectionCard>
            </div>
        </AuthenticatedLayout>
    );
}
