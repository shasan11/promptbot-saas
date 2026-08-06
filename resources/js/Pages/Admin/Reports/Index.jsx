import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

const inputClass = 'rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950';
const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function ExportLink({ type, filters }) {
    return (
        <a href={route('superadmin.reports.export', { type, ...filters })} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold capitalize text-slate-700 shadow-sm hover:bg-slate-50">
            Export {type}
        </a>
    );
}

function Panel({ title, children }) {
    return (
        <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-base font-bold text-slate-950">{title}</h2>
            <div className="mt-4">{children}</div>
        </section>
    );
}

export default function Index({ filters = {}, currency = 'USD', stats = {}, subscriptionStatuses = [], invoiceStatuses = [], paymentProviders = [], ticketStatuses = [], planMix = [], recentPayments = [], recentTickets = [] }) {
    const { data, setData } = useForm({ from: filters.from || '', to: filters.to || '' });

    const apply = (event) => {
        event.preventDefault();
        router.get(route('superadmin.reports.index'), data, { preserveState: true, preserveScroll: true });
    };

    const statusColumns = [
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Records', dataIndex: 'total' },
    ];

    return (
        <AuthenticatedLayout header={<PageHeader title="Reports" subtitle="Operational and financial reporting with date filters and CSV exports." />}>
            <Head title="Reports" />

            <form onSubmit={apply} className="mb-5 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <label>
                    <span className="block text-xs font-semibold uppercase tracking-wide text-slate-500">From</span>
                    <input type="date" className={`${inputClass} mt-1`} value={data.from} onChange={(event) => setData('from', event.target.value)} />
                </label>
                <label>
                    <span className="block text-xs font-semibold uppercase tracking-wide text-slate-500">To</span>
                    <input type="date" className={`${inputClass} mt-1`} value={data.to} onChange={(event) => setData('to', event.target.value)} />
                </label>
                <button className="rounded-md bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Apply range</button>
            </form>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <StatCard title="New tenants" value={stats.newTenants ?? 0} tone="blue" />
                <StatCard title="Active subscriptions" value={stats.activeSubscriptions ?? 0} tone="slate" />
                <StatCard title={`Invoiced (${currency})`} value={money(stats.invoiced)} tone="amber" />
                <StatCard title={`Collected (${currency})`} value={money(stats.collected)} tone="emerald" />
                <StatCard title={`Refunded (${currency})`} value={money(stats.refunded)} tone="rose" />
                <StatCard title="Open tickets" value={stats.openTickets ?? 0} tone="slate" />
            </div>

            <div className="mt-6 flex flex-wrap gap-2">
                {['tenants', 'subscriptions', 'invoices', 'payments', 'tickets'].map((type) => <ExportLink key={type} type={type} filters={filters} />)}
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Panel title="Subscription status"><DataTable columns={statusColumns} dataSource={subscriptionStatuses} rowKey="status" /></Panel>
                <Panel title="Ticket status"><DataTable columns={statusColumns} dataSource={ticketStatuses} rowKey="status" /></Panel>
                <Panel title="Invoice status by currency">
                    <DataTable columns={[
                        ...statusColumns,
                        { title: 'Currency', dataIndex: 'currency' },
                        { title: 'Amount', dataIndex: 'amount', render: (value, row) => `${row.currency} ${money(value)}` },
                    ]} dataSource={invoiceStatuses} rowKey={(row) => `${row.status}-${row.currency}`} />
                </Panel>
                <Panel title="Payments by provider and currency">
                    <DataTable columns={[
                        { title: 'Provider', dataIndex: 'provider', render: (value) => value.replaceAll('_', ' ') },
                        { title: 'Currency', dataIndex: 'currency' },
                        { title: 'Records', dataIndex: 'total' },
                        { title: 'Amount', dataIndex: 'amount', render: (value, row) => `${row.currency} ${money(value)}` },
                        { title: 'Refunded', dataIndex: 'refunded', render: (value, row) => `${row.currency} ${money(value)}` },
                    ]} dataSource={paymentProviders} rowKey={(row) => `${row.provider}-${row.currency}`} />
                </Panel>
                <Panel title="Active subscriptions by plan">
                    <DataTable columns={[
                        { title: 'Plan', dataIndex: 'name' },
                        { title: 'Subscriptions', dataIndex: 'subscriptions_count' },
                        { title: 'Currency', dataIndex: 'currency' },
                    ]} dataSource={planMix} rowKey="id" />
                </Panel>
                <Panel title="Recent payments">
                    <DataTable columns={[
                        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '-' },
                        { title: 'Invoice', dataIndex: ['invoice', 'number'], render: (value) => value || '-' },
                        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
                        { title: 'Amount', dataIndex: 'amount', render: (value, payment) => `${payment.currency} ${money(value)}` },
                    ]} dataSource={recentPayments} rowKey="id" />
                </Panel>
            </div>

            <div className="mt-6">
                <Panel title="Recent tickets">
                    <DataTable columns={[
                        { title: 'Number', dataIndex: 'number' },
                        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '-' },
                        { title: 'Subject', dataIndex: 'subject' },
                        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
                        { title: 'Priority', dataIndex: 'priority', render: (value) => <StatusBadge status={value} /> },
                        { title: 'Assigned', dataIndex: ['assignee', 'name'], render: (value) => value || 'Unassigned' },
                    ]} dataSource={recentTickets} rowKey="id" />
                </Panel>
            </div>
        </AuthenticatedLayout>
    );
}
