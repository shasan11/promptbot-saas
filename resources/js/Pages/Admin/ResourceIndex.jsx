import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

function valueFor(record, column) {
    const value = record?.[column.key];

    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (column.type === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

export default function ResourceIndex({ title, table, columns = [], records = {}, filters = {}, meta = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const rows = records.data || [];

    const submit = (event) => {
        event.preventDefault();
        router.get(window.location.pathname, { ...filters, search }, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={title} subtitle={meta.description || `Operational records from ${table}.`} />}>
            <Head title={title} />
            <form onSubmit={submit} className="mb-4 flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:flex-row">
                <input
                    className="min-h-10 flex-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search records"
                />
                <button className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700" type="submit">
                    Search
                </button>
            </form>
            <DataTable
                columns={columns.map((column) => ({
                    title: column.label,
                    dataIndex: column.key,
                    render: (_, record) => valueFor(record, column),
                }))}
                dataSource={rows}
            />
            <div className="mt-4 text-sm font-medium text-slate-500">
                Showing {records.from || 0}-{records.to || 0} of {records.total || 0}
            </div>
        </AuthenticatedLayout>
    );
}
