import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

function Pagination({ links = [] }) {
    if (!links.length) {
        return null;
    }

    const clean = (label) => label.replace('&laquo;', '<').replace('&raquo;', '>');

    return (
        <div className="mt-4 flex flex-wrap gap-2">
            {links.map((link, index) => (
                <button
                    key={`${link.label}-${index}`}
                    type="button"
                    disabled={!link.url}
                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                    className={`rounded-md border px-3 py-2 text-sm font-semibold shadow-sm ${link.active ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'} disabled:cursor-not-allowed disabled:opacity-40`}
                >
                    {clean(link.label)}
                </button>
            ))}
        </div>
    );
}

export default function Index({ tenants, filters = {} }) {
    const { data, setData } = useForm({
        search: filters.search || '',
        status: filters.status || '',
    });
    const rows = tenants?.data || [];
    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('superadmin.tenants.index'), data, { preserveState: true, preserveScroll: true });
    };

    const columns = [
        {
            title: 'Company',
            dataIndex: 'company_name',
            render: (value, tenant) => (
                <div>
                    <Link href={route('superadmin.tenants.show', tenant.public_uuid || tenant.id)} className="font-semibold text-slate-950 hover:text-blue-700">
                        {value}
                    </Link>
                    <div className="mt-1 font-mono text-xs text-slate-500">{tenant.slug || tenant.id}</div>
                </div>
            ),
        },
        { title: 'Status', dataIndex: 'status', render: (status) => <StatusBadge status={status} /> },
        { title: 'Plan', dataIndex: ['plan', 'name'], render: (value) => value || '-' },
        {
            title: 'Domains',
            dataIndex: 'domains',
            render: (domains = []) => domains.length ? (
                <div className="flex flex-wrap gap-2">
                    {domains.map((domain) => <span key={domain.id} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{domain.domain}</span>)}
                </div>
            ) : '-',
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Tenants"
                    subtitle="Create, inspect, and operate customer workspaces."
                    actions={<Link href={route('superadmin.tenants.create')} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Create tenant</Link>}
                />
            }
        >
            <Head title="Tenants" />
            <form onSubmit={applyFilters} className="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_220px_auto]">
                <input
                    value={data.search}
                    onChange={(event) => setData('search', event.target.value)}
                    placeholder="Search company or slug"
                    className="rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950"
                />
                <select
                    value={data.status}
                    onChange={(event) => setData('status', event.target.value)}
                    className="rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950"
                >
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="provisioning">Provisioning</option>
                    <option value="pending">Pending</option>
                    <option value="suspended">Suspended</option>
                    <option value="failed">Failed</option>
                </select>
                <button className="rounded-md bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Filter</button>
            </form>
            <DataTable columns={columns} dataSource={rows} />
            <Pagination links={tenants?.links} />
        </AuthenticatedLayout>
    );
}
