import PageHeader from '@/Components/Superadmin/PageHeader';
import SecretInput from '@/Components/Superadmin/SecretInput';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plug } from 'lucide-react';

export default function Form({ provider = null, driverOptions }) {
    const isEdit = !!provider;
    const driverMeta = driverOptions.find((option) => option.value === (provider?.driver || driverOptions[0]?.value));

    const { data, setData, post, put, processing, errors } = useForm({
        driver: provider?.driver || driverOptions[0]?.value || 'openai',
        name: provider?.name || '',
        slug: provider?.slug || '',
        base_url: provider?.base_url || '',
        api_key: '',
        organization_id: '',
        is_enabled: provider?.is_enabled ?? true,
        priority: provider?.priority ?? 100,
        timeout_seconds: provider?.timeout_seconds ?? '',
        max_retries: provider?.max_retries ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        isEdit ? put(route('superadmin.ai.providers.update', provider.id)) : post(route('superadmin.ai.providers.store'));
    };

    const removeKey = () => {
        if (confirm('Remove the stored API key for this provider?')) {
            router.delete(route('superadmin.ai.providers.key.remove', provider.id), { preserveScroll: true });
        }
    };

    const test = () => router.post(route('superadmin.ai.providers.test', provider.id), {}, { preserveScroll: true });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={isEdit ? `Configure ${provider.name}` : 'Add AI Provider'}
                    subtitle="API credentials are encrypted at rest and never sent back to the browser after saving."
                    actions={<Link href={route('superadmin.ai.providers.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to providers</Link>}
                />
            }
        >
            <Head title={isEdit ? `Configure ${provider.name}` : 'Add AI Provider'} />

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6">
                {isEdit && provider.last_test_status && (
                    <Alert tone={provider.last_test_status === 'success' ? 'success' : 'warning'} title="Last connection test">
                        {provider.last_test_message} {provider.last_tested_at && <span className="opacity-75">&middot; {new Date(provider.last_tested_at).toLocaleString()}</span>}
                    </Alert>
                )}

                <SectionCard title="Provider details">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField label="Driver" required>
                            <Select value={data.driver} disabled={isEdit} onChange={(e) => setData('driver', e.target.value)}>
                                {driverOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </Select>
                        </FormField>
                        <FormField label="Display name" required error={errors.name}>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. OpenAI (Production)" />
                        </FormField>
                        {isEdit && (
                            <FormField label="Slug" hint="Used internally to reference this provider.">
                                <Input value={data.slug} disabled className="bg-slate-50 text-slate-500" />
                            </FormField>
                        )}
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
                        <FormField label="Provider enabled">
                            <div className="mt-2">
                                <Switch checked={data.is_enabled} onChange={(value) => setData('is_enabled', value)} />
                            </div>
                        </FormField>
                    </div>
                </SectionCard>

                <SectionCard title="Credentials">
                    <div className="grid gap-5 md:grid-cols-2">
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
                    </div>
                </SectionCard>

                <SectionCard title="Advanced">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField label="Timeout (seconds)" optional hint="Falls back to the global AI setting when blank.">
                            <Input type="number" value={data.timeout_seconds} onChange={(e) => setData('timeout_seconds', e.target.value)} />
                        </FormField>
                        <FormField label="Max retries" optional hint="Falls back to the global AI setting when blank.">
                            <Input type="number" value={data.max_retries} onChange={(e) => setData('max_retries', e.target.value)} />
                        </FormField>
                    </div>
                </SectionCard>

                <div className="flex items-center justify-between gap-3">
                    <div>
                        {isEdit && <Button type="button" variant="secondary" icon={Plug} onClick={test}>Test connection</Button>}
                    </div>
                    <div className="flex items-center gap-3">
                        <Link href={route('superadmin.ai.providers.index')} className="rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Cancel</Link>
                        <Button type="submit" variant="brand" loading={processing}>{isEdit ? 'Save changes' : 'Add provider'}</Button>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
