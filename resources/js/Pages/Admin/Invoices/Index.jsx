import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import EmptyState from '@/Components/UI/EmptyState';
import FilterBar from '@/Components/UI/FilterBar';
import Select from '@/Components/UI/Select';
import Tabs from '@/Components/UI/Tabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Plus, ReceiptText } from 'lucide-react';
import { useState } from 'react';

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'open', label: 'Open' },
    { value: 'paid', label: 'Paid' },
    { value: 'void', label: 'Void' },
];

export default function Index({ invoices, tenants = [], filters = {} }) {
    const [status, setStatus] = useState(filters.status || '');
    const [tenantId, setTenantId] = useState(filters.tenant_id || '');
    const rows = invoices?.data || [];

    const applyFilters = (next = {}) => {
        const params = { status, tenant_id: tenantId, ...next };
        setStatus(params.status);
        setTenantId(params.tenant_id);
        router.get(route('superadmin.billing.invoices.index'), params, { preserveState: true, preserveScroll: true });
    };

    const isOverdue = (invoice) => invoice.status === 'open' && invoice.due_on && new Date(invoice.due_on) < new Date();

    const columns = [
        {
            title: 'Number',
            dataIndex: 'number',
            render: (value, invoice) => (
                <Link href={route('superadmin.billing.invoices.show', invoice.id)} className="font-mono text-sm font-semibold text-slate-900 hover:text-brand-700">{value}</Link>
            ),
        },
        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '—' },
        {
            title: 'Status',
            dataIndex: 'status',
            render: (status, invoice) => (
                <div className="flex items-center gap-2">
                    <StatusBadge status={status} />
                    {isOverdue(invoice) && <Badge tone="danger">Overdue</Badge>}
                </div>
            ),
        },
        { title: 'Total', dataIndex: 'total', render: (value, invoice) => <span className="font-mono">{invoice.currency} {Number(value).toFixed(2)}</span>, align: 'right' },
        { title: 'Issued', dataIndex: 'issued_on' },
        { title: 'Due', dataIndex: 'due_on', render: (value) => value || '—' },
        {
            title: '',
            dataIndex: 'id',
            render: (_, invoice) => (
                <DropdownMenu items={[{ label: 'View invoice', icon: Eye, onClick: () => router.visit(route('superadmin.billing.invoices.show', invoice.id)) }]} />
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="Invoices"
                    subtitle="Issue and track manual invoices for tenants."
                    actions={<Button href={route('superadmin.billing.invoices.create')} variant="brand" icon={Plus}>Create invoice</Button>}
                />
            )}
        >
            <Head title="Invoices" />

            <Tabs items={STATUS_TABS} active={status} onChange={(value) => applyFilters({ status: value })} />

            <div className="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
                <FilterBar>
                    <Select value={tenantId} onChange={(event) => applyFilters({ tenant_id: event.target.value })} className="w-56">
                        <option value="">All tenants</option>
                        {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                    </Select>
                </FilterBar>
            </div>

            <div className="mt-4">
                {rows.length ? (
                    <>
                        <DataTable columns={columns} dataSource={rows} rowKey="id" />
                        <Pagination links={invoices?.links} />
                    </>
                ) : (
                    <EmptyState icon={ReceiptText} title="No invoices found" description="Try a different status or tenant filter, or create a new invoice." action={<Button href={route('superadmin.billing.invoices.create')} variant="brand" icon={Plus}>Create invoice</Button>} />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
