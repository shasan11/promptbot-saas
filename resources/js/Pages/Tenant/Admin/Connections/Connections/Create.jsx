import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import Input from '@/Components/UI/Input';
import Textarea from '@/Components/UI/Textarea';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, useForm } from '@inertiajs/react';
import { PlugZap } from 'lucide-react';

export default function Create({ integrations, selectedIntegration, connectionTypes, authTypes, environments }) {
    const form = useForm({
        connection_integration_id: selectedIntegration?.id || integrations[0]?.id || '',
        name: selectedIntegration ? `${selectedIntegration.name} connection` : '',
        description: '',
        connection_type: 'application',
        auth_type: selectedIntegration?.auth_methods?.[0] || 'none',
        environment: 'sandbox',
        provider_account_name: '',
        usage: ['knowledge_base'],
        configuration: { read_only: true },
        credential: {},
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(route('tenant.admin.connections.store'));
    };

    return (
        <ConnectionsShell title="Add connection" description="Select an app, configure the tenant-owned connection, then test and discover resources from the detail screen.">
            <Head title="Add connection" />
            <form onSubmit={submit} className="grid gap-6 xl:grid-cols-[1fr_360px]">
                <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="md:col-span-2">
                            <span className="text-sm font-semibold text-slate-700">App</span>
                            <Select value={form.data.connection_integration_id} onChange={(event) => form.setData('connection_integration_id', event.target.value)} className="mt-1">
                                {integrations.map((integration) => <option key={integration.id} value={integration.id}>{integration.name} · {integration.category}</option>)}
                            </Select>
                        </label>
                        <label>
                            <span className="text-sm font-semibold text-slate-700">Connection name</span>
                            <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="mt-1" required />
                        </label>
                        <label>
                            <span className="text-sm font-semibold text-slate-700">Connected account</span>
                            <Input value={form.data.provider_account_name} onChange={(event) => form.setData('provider_account_name', event.target.value)} className="mt-1" />
                        </label>
                        <label>
                            <span className="text-sm font-semibold text-slate-700">Connection type</span>
                            <Select value={form.data.connection_type} onChange={(event) => form.setData('connection_type', event.target.value)} className="mt-1">{connectionTypes.map((item) => <option key={item} value={item}>{item.replaceAll('_', ' ')}</option>)}</Select>
                        </label>
                        <label>
                            <span className="text-sm font-semibold text-slate-700">Authentication</span>
                            <Select value={form.data.auth_type} onChange={(event) => form.setData('auth_type', event.target.value)} className="mt-1">{authTypes.map((item) => <option key={item} value={item}>{item.replaceAll('_', ' ')}</option>)}</Select>
                        </label>
                        <label>
                            <span className="text-sm font-semibold text-slate-700">Environment</span>
                            <Select value={form.data.environment} onChange={(event) => form.setData('environment', event.target.value)} className="mt-1">{environments.map((item) => <option key={item} value={item}>{item}</option>)}</Select>
                        </label>
                        <label className="md:col-span-2">
                            <span className="text-sm font-semibold text-slate-700">Description</span>
                            <Textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="mt-1" rows={4} />
                        </label>
                    </div>
                    <div className="mt-6 flex justify-end gap-2">
                        <Button href={route('tenant.admin.connections.apps.index')} variant="secondary">Browse apps</Button>
                        <Button type="submit" variant="brand" icon={PlugZap} loading={form.processing}>Create connection</Button>
                    </div>
                </div>
                <aside className="rounded-lg border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-soft">
                    <h2 className="font-semibold text-slate-900">Connection wizard</h2>
                    <ol className="mt-4 space-y-3">
                        {['Select app', 'Authenticate', 'Configure connection', 'Select resources', 'Configure usage', 'Sync settings', 'Review', 'Connect'].map((step, index) => (
                            <li key={step} className="flex gap-2"><span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-600">{index + 1}</span>{step}</li>
                        ))}
                    </ol>
                </aside>
            </form>
        </ConnectionsShell>
    );
}
