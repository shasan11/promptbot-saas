import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

const STATUSES = ['draft', 'open', 'paid', 'void'];

export default function Index({ invoices, tenants = [], filters = {} }) {
    const { data, setData } = useForm({
        status: filters.status || '',
        tenant_id: filters.tenant_id || '',
    });
    const rows = invoices?.data || [];

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('superadmin.billing.invoices.index'), data, { preserveState: true, preserveScroll: true });
    };

    const columns = [
        {
            title: 'Number',
            dataIndex: 'number',
            render: (value, invoice) => (
                <Link href={route('superadmin.billing.invoices.show', invoice.id)} className="font-mono text-sm font-semibold text-slate-950 hover:text-blue-700">{value}</Link>
            ),
        },
        { title: 'Tenant', dataIndex: ['tenant', 'company_name'], render: (value) => value || '-' },
        { title: 'Status', dataIndex: 'status', render: (status) => <StatusBadge status={status} /> },
        { title: 'Total', dataIndex: 'total', render: (value, invoice) => `${invoice.currency} ${value}` },
        { title: 'Issued', dataIndex: 'issued_on' },
        { title: 'Due', dataIndex: 'due_on', render: (value) => value || '-' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Invoices"
                    subtitle="Issue and track manual invoices for tenants."
                    actions={<Link href={route('superadmin.billing.invoices.create')} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Create invoice</Link>}
                />
            }
        >
            <Head title="Invoices" />
            <form onSubmit={applyFilters} className="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[220px_220px_auto]">
                <select value={data.status} onChange={(event) => setData('status', event.target.value)} className="rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950">
                    <option value="">All statuses</option>
                    {STATUSES.map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
                <select value={data.tenant_id} onChange={(event) => setData('tenant_id', event.target.value)} className="rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950">
                    <option value="">All tenants</option>
                    {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                </select>
                <button className="rounded-md bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Filter</button>
            </form>
            <DataTable columns={columns} dataSource={rows} rowKey="id" />
            <Pagination links={invoices?.links} />
        </AuthenticatedLayout>
    );
}
