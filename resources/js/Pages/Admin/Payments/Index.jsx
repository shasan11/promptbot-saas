import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatCard from '@/Components/Superadmin/StatCard';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import EmptyState from '@/Components/UI/EmptyState';
import FilterBar from '@/Components/UI/FilterBar';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CreditCard, Eye, Plus } from 'lucide-react';
import { useState } from 'react';

const providers = ['manual', 'bank_transfer', 'stripe', 'paypal', 'khalti', 'esewa'];
const statuses = ['pending', 'paid', 'failed', 'partially_refunded', 'refunded'];

const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Index({ payments, tenants = [], filters = {}, stats = {} }) {
    const { auth } = usePage().props;
    const canManage = auth?.permissions?.includes('payments.manage');
    const currency = stats.currency || 'USD';
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [provider, setProvider] = useState(filters.provider || '');
    const [tenantId, setTenantId] = useState(filters.tenant_id || '');

    const applyFilters = (next = {}) => {
        router.get(route('superadmin.billing.payments.index'), { search, status, provider, tenant_id: tenantId, ...next }, { preserveState: true, preserveScroll: true });
    };

    const columns = [
        {
            title: 'Payment',
            dataIndex: 'id',
            render: (value, payment) => (
                <div>
                    <Link href={route('superadmin.billing.payments.show', value)} className="font-mono text-sm font-semibold text-slate-900 hover:text-brand-700">
                        {payment.provider_reference || value.slice(0, 8)}
                    </Link>
                    <div className="mt-1 text-xs capitalize text-slate-500">{payment.provider.replaceAll('_', ' ')}</div>
                </div>
            ),
        },
        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '—' },
        { title: 'Invoice', dataIndex: ['invoice', 'number'], render: (value) => value || '—' },
        { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge status={value} /> },
        { title: 'Amount', dataIndex: 'amount', render: (value, payment) => <span className="font-mono">{payment.currency} {money(value)}</span> },
        { title: 'Refunded', dataIndex: 'refunded_amount', render: (value, payment) => <span className="font-mono">{payment.currency} {money(value)}</span> },
        { title: 'Created', dataIndex: 'created_at', render: (value) => value ? new Date(value).toLocaleString() : '—' },
        {
            title: '',
            dataIndex: 'id',
            render: (_, payment) => (
                <DropdownMenu items={[{ label: 'View payment', icon: Eye, onClick: () => router.visit(route('superadmin.billing.payments.show', payment.id)) }]} />
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="Payments"
                    subtitle="Record, reconcile, and refund tenant payments."
                    actions={canManage && <Button href={route('superadmin.billing.payments.create')} variant="brand" icon={Plus}>Record payment</Button>}
                />
            )}
        >
            <Head title="Payments" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Payment records" value={stats.total ?? 0} tone="slate" />
                <StatCard title={`Gross paid (${currency})`} value={money(stats.paid)} tone="emerald" />
                <StatCard title="Pending" value={stats.pending ?? 0} tone="amber" />
                <StatCard title={`Refunded (${currency})`} value={money(stats.refunded)} tone="rose" />
            </div>

            <div className="my-5 rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
                <FilterBar>
                    <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); applyFilters({ search: '' }); }} placeholder="Reference, invoice, or tenant" className="w-full max-w-xs" />
                    <Select value={status} onChange={(event) => { setStatus(event.target.value); applyFilters({ status: event.target.value }); }} className="w-44">
                        <option value="">All statuses</option>
                        {statuses.map((item) => <option key={item} value={item}>{item.replaceAll('_', ' ')}</option>)}
                    </Select>
                    <Select value={provider} onChange={(event) => { setProvider(event.target.value); applyFilters({ provider: event.target.value }); }} className="w-44">
                        <option value="">All providers</option>
                        {providers.map((item) => <option key={item} value={item}>{item.replaceAll('_', ' ')}</option>)}
                    </Select>
                    <Select value={tenantId} onChange={(event) => { setTenantId(event.target.value); applyFilters({ tenant_id: event.target.value }); }} className="w-52">
                        <option value="">All tenants</option>
                        {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                    </Select>
                    <Button variant="secondary" size="sm" onClick={() => applyFilters()}>Apply</Button>
                </FilterBar>
            </div>

            {(payments?.data || []).length ? (
                <>
                    <DataTable columns={columns} dataSource={payments?.data || []} rowKey="id" />
                    <Pagination links={payments?.links} />
                </>
            ) : (
                <EmptyState icon={CreditCard} title="No payments found" description="Try a different search term or filter." />
            )}
        </AuthenticatedLayout>
    );
}
