import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Logs({ logs, filters, statusOptions, purposeOptions, providerOptions }) {
    const [form, setForm] = useState({
        status: filters.status || '',
        provider_driver: filters.provider_driver || '',
        purpose: filters.purpose || '',
        tenant_id: filters.tenant_id || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('superadmin.ai.logs.index'), Object.fromEntries(Object.entries(form).filter(([, v]) => v)), { preserveState: true });
    };

    const clearFilters = () => {
        setForm({ status: '', provider_driver: '', purpose: '', tenant_id: '', date_from: '', date_to: '' });
        router.get(route('superadmin.ai.logs.index'));
    };

    const columns = [
        { title: 'Date', dataIndex: 'created_at', render: (value) => value ? new Date(value).toLocaleString() : '—' },
        { title: 'Tenant', dataIndex: 'tenant_id', render: (value) => value || 'Platform' },
        { title: 'Provider', dataIndex: 'provider_name', render: (value, log) => value ? `${value} (${log.provider_driver})` : '—' },
        { title: 'Model', dataIndex: 'model_key', render: (value) => value ? <span className="font-mono text-xs">{value}</span> : '—' },
        { title: 'Purpose', dataIndex: 'purpose' },
        {
            title: 'Tokens',
            dataIndex: 'total_tokens',
            render: (value, log) => `${value} (${log.prompt_tokens} in / ${log.completion_tokens} out)`,
        },
        { title: 'Cost', dataIndex: 'estimated_cost', render: (value) => value > 0 ? `$${value.toFixed(4)}` : '—' },
        { title: 'Latency', dataIndex: 'latency_ms', render: (value) => value ? `${value} ms` : '—' },
        {
            title: 'Status',
            dataIndex: 'status',
            render: (value, log) => (
                <div>
                    <StatusBadge status={value} />
                    {log.error_code && <div className="mt-1 text-xs text-rose-600">{log.error_code}</div>}
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout header={<PageHeader title="AI Logs" subtitle="Every AI request attempt, including failures, with sanitized error details." />}>
            <Head title="AI Logs" />

            <Card className="mb-4">
                <form onSubmit={applyFilters} className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <Select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                        <option value="">Any status</option>
                        {statusOptions.map((option) => <option key={option} value={option}>{option}</option>)}
                    </Select>
                    <Select value={form.provider_driver} onChange={(e) => setForm({ ...form, provider_driver: e.target.value })}>
                        <option value="">Any provider</option>
                        {providerOptions.map((option) => <option key={option} value={option}>{option}</option>)}
                    </Select>
                    <Select value={form.purpose} onChange={(e) => setForm({ ...form, purpose: e.target.value })}>
                        <option value="">Any purpose</option>
                        {purposeOptions.map((option) => <option key={option} value={option}>{option}</option>)}
                    </Select>
                    <Input placeholder="Tenant ID" value={form.tenant_id} onChange={(e) => setForm({ ...form, tenant_id: e.target.value })} />
                    <Input type="date" value={form.date_from} onChange={(e) => setForm({ ...form, date_from: e.target.value })} />
                    <Input type="date" value={form.date_to} onChange={(e) => setForm({ ...form, date_to: e.target.value })} />
                    <div className="flex gap-2 sm:col-span-3 lg:col-span-6">
                        <Button type="submit" variant="brand" size="sm">Apply filters</Button>
                        <Button type="button" variant="secondary" size="sm" onClick={clearFilters}>Clear</Button>
                    </div>
                </form>
            </Card>

            <DataTable columns={columns} dataSource={logs.data} emptyText="No AI requests logged yet." />
            <Pagination links={logs.links} />
        </AuthenticatedLayout>
    );
}
