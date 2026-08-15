import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import SecretInput from '@/Components/Superadmin/SecretInput';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import DangerConfirmDialog from '@/Components/UI/DangerConfirmDialog';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Plug, Plus } from 'lucide-react';
import { useState } from 'react';

function ProviderModal({ open, onClose, provider, driverOptions }) {
    const isEdit = !!provider;
    const driverMeta = driverOptions.find((option) => option.value === (provider?.driver || driverOptions[0]?.value));

    const { data, setData, post, put, processing, errors, reset } = useForm({
        driver: provider?.driver || driverOptions[0]?.value || 'openai',
        name: provider?.name || '',
        base_url: provider?.base_url || '',
        api_key: '',
        organization_id: '',
        is_enabled: provider?.is_enabled ?? true,
        priority: provider?.priority ?? 100,
        timeout_seconds: provider?.timeout_seconds ?? '',
        max_retries: provider?.max_retries ?? '',
    });

    const close = () => { reset(); onClose(); };

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };
        isEdit ? put(route('superadmin.ai.providers.update', provider.id), options) : post(route('superadmin.ai.providers.store'), options);
    };

    const removeKey = () => {
        if (confirm('Remove the stored API key for this provider?')) {
            router.delete(route('superadmin.ai.providers.key.remove', provider.id), { preserveScroll: true });
        }
    };

    const test = () => router.post(route('superadmin.ai.providers.test', provider.id), {}, { preserveScroll: true });

    return (
        <Modal open={open} onClose={close} title={isEdit ? `Configure ${provider.name}` : 'Add AI provider'} description="API credentials are encrypted at rest and never sent back to the browser after saving." size="xl">
            <form onSubmit={submit} className="space-y-5">
                {isEdit && provider.last_test_status && (
                    <Alert tone={provider.last_test_status === 'success' ? 'success' : 'warning'} title="Last connection test">
                        {provider.last_test_message} {provider.last_tested_at && <span className="opacity-75">&middot; {new Date(provider.last_tested_at).toLocaleString()}</span>}
                    </Alert>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label="Driver" required>
                        <Select value={data.driver} disabled={isEdit} onChange={(e) => setData('driver', e.target.value)}>
                            {driverOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </Select>
                    </FormField>
                    <FormField label="Display name" required error={errors.name}>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. OpenAI (Production)" />
                    </FormField>
                    <FormField
                        label="Base URL"
                        optional={driverMeta?.value !== 'custom' && driverMeta?.value !== 'ollama'}
                        required={driverMeta?.value === 'custom' || driverMeta?.value === 'ollama'}
                        error={errors.base_url}
                        hint={driverMeta?.default_base_url ? `Defaults to ${driverMeta.default_base_url}` : 'Required for a custom or self-hosted endpoint.'}
                    >
                        <Input value={data.base_url} onChange={(e) => setData('base_url', e.target.value)} placeholder={driverMeta?.default_base_url || 'https://'} />
                    </FormField>
                    <FormField label="Priority" hint="Lower numbers are tried first in the fallback chain.">
                        <Input type="number" value={data.priority} onChange={(e) => setData('priority', e.target.value)} />
                    </FormField>
                    <FormField
                        label="API key"
                        required={driverMeta?.requires_api_key}
                        optional={!driverMeta?.requires_api_key}
                        error={errors.api_key}
                        hint={isEdit && provider.has_key ? `Configured: ${provider.masked_key}. Leave blank to keep it.` : driverMeta?.requires_api_key ? 'Stored encrypted.' : 'Not required for this driver.'}
                    >
                        <div className="flex gap-2">
                            <SecretInput value={data.api_key} onChange={(e) => setData('api_key', e.target.value)} placeholder={isEdit && provider.has_key ? 'Leave blank to keep current key' : ''} />
                            {isEdit && provider.has_key && <Button type="button" variant="secondary" size="sm" onClick={removeKey}>Remove</Button>}
                        </div>
                    </FormField>
                    <FormField label="Organization / project ID" optional error={errors.organization_id}>
                        <Input value={data.organization_id} onChange={(e) => setData('organization_id', e.target.value)} placeholder={isEdit && provider.has_organization ? 'Configured — leave blank to keep' : ''} />
                    </FormField>
                    <FormField label="Timeout (seconds)" optional hint="Falls back to the global AI setting when blank.">
                        <Input type="number" value={data.timeout_seconds} onChange={(e) => setData('timeout_seconds', e.target.value)} />
                    </FormField>
                    <FormField label="Max retries" optional hint="Falls back to the global AI setting when blank.">
                        <Input type="number" value={data.max_retries} onChange={(e) => setData('max_retries', e.target.value)} />
                    </FormField>
                    <FormField label="Provider enabled">
                        <div className="mt-2">
                            <Switch checked={data.is_enabled} onChange={(value) => setData('is_enabled', value)} />
                        </div>
                    </FormField>
                </div>

                <div className="flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <div>
                        {isEdit && <Button type="button" variant="secondary" size="sm" icon={Plug} onClick={test}>Test connection</Button>}
                    </div>
                    <div className="flex items-center gap-3">
                        <Button type="button" variant="secondary" onClick={close}>Cancel</Button>
                        <Button type="submit" variant="brand" loading={processing}>{isEdit ? 'Save changes' : 'Add provider'}</Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}

export default function Index({ providers, driverOptions }) {
    const [modalProvider, setModalProvider] = useState(undefined);
    const [pendingDelete, setPendingDelete] = useState(null);

    const toggle = (provider) => router.post(route('superadmin.ai.providers.toggle', provider.id), {}, { preserveScroll: true });
    const test = (provider) => router.post(route('superadmin.ai.providers.test', provider.id), {}, { preserveScroll: true });

    const columns = [
        {
            title: 'Provider',
            dataIndex: 'name',
            render: (value, provider) => (
                <div>
                    <button type="button" onClick={() => setModalProvider(provider)} className="font-semibold text-slate-950 hover:text-blue-700">{value}</button>
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
                    <button type="button" onClick={() => setModalProvider(provider)} className="text-xs font-semibold text-blue-700 hover:underline">Configure</button>
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
                    actions={<Button icon={Plus} onClick={() => setModalProvider(null)}>Add provider</Button>}
                />
            }
        >
            <Head title="AI Providers" />

            <DataTable columns={columns} dataSource={providers} emptyText="No providers configured yet." />

            {modalProvider !== undefined && (
                <ProviderModal
                    open={modalProvider !== undefined}
                    onClose={() => setModalProvider(undefined)}
                    provider={modalProvider}
                    driverOptions={driverOptions}
                />
            )}

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
