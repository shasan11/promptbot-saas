import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import Switch from '@/Components/UI/Switch';
import DangerConfirmDialog from '@/Components/UI/DangerConfirmDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Plug, Plus } from 'lucide-react';
import { useState } from 'react';

export default function Index({ providers }) {
    const [pendingDelete, setPendingDelete] = useState(null);

    const toggle = (provider) => router.post(route('superadmin.ai.providers.toggle', provider.id), {}, { preserveScroll: true });
    const test = (provider) => router.post(route('superadmin.ai.providers.test', provider.id), {}, { preserveScroll: true });

    const columns = [
        {
            title: 'Provider',
            dataIndex: 'name',
            render: (value, provider) => (
                <div>
                    <Link href={route('superadmin.ai.providers.edit', provider.id)} className="font-semibold text-slate-950 hover:text-blue-700">{value}</Link>
                    <div className="mt-1 text-xs text-slate-500">{provider.driver_label} &middot; {provider.models_count} model{provider.models_count === 1 ? '' : 's'}</div>
                </div>
            ),
        },
        {
            title: 'API key',
            dataIndex: 'masked_key',
            render: (value, provider) => provider.has_key ? <span className="font-mono text-xs text-slate-600">{value}</span> : <span className="text-xs text-slate-400">Not configured</span>,
        },
        {
            title: 'Status',
            dataIndex: 'is_enabled',
            render: (value, provider) => (
                <div className="flex items-center gap-2">
                    <StatusBadge status={value ? 'active' : 'cancelled'} />
                    {provider.last_test_status && <StatusBadge status={provider.last_test_status} />}
                </div>
            ),
        },
        {
            title: 'Last tested',
            dataIndex: 'last_tested_at',
            render: (value) => value ? new Date(value).toLocaleString() : 'Never',
        },
        {
            title: '',
            dataIndex: 'id',
            render: (_value, provider) => (
                <div className="flex flex-wrap items-center justify-end gap-2">
                    <Button size="sm" variant="secondary" icon={Plug} onClick={() => test(provider)}>Test</Button>
                    <Switch checked={provider.is_enabled} onChange={() => toggle(provider)} />
                    <Link href={route('superadmin.ai.providers.edit', provider.id)} className="text-xs font-semibold text-blue-700 hover:underline">Configure</Link>
                    <button type="button" onClick={() => setPendingDelete(provider)} className="text-xs font-semibold text-rose-600 hover:underline">Delete</button>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="AI Providers"
                    subtitle="Configure the LLM providers PromptBot may use. Credentials are encrypted at rest and never sent back to the browser."
                    actions={<Link href={route('superadmin.ai.providers.create')} className="inline-flex items-center gap-2 rounded-md bg-navy-800 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-navy-900"><Plus className="h-4 w-4" /> Add provider</Link>}
                />
            }
        >
            <Head title="AI Providers" />

            <DataTable columns={columns} dataSource={providers} emptyText="No providers configured yet." />

            <DangerConfirmDialog
                open={!!pendingDelete}
                title={`Delete ${pendingDelete?.name}?`}
                consequence="This removes the provider, its encrypted credentials, and every model registered under it."
                confirmation={pendingDelete?.slug}
                confirmLabel="Delete provider"
                onCancel={() => setPendingDelete(null)}
                onConfirm={() => {
                    router.delete(route('superadmin.ai.providers.destroy', pendingDelete.id), {
                        onFinish: () => setPendingDelete(null),
                    });
                }}
            />
        </AuthenticatedLayout>
    );
}
