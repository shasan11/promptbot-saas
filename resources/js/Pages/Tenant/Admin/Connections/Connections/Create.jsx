import Button from '@/Components/UI/Button';
import Select from '@/Components/UI/Select';
import Input from '@/Components/UI/Input';
import Textarea from '@/Components/UI/Textarea';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { KeyRound, PlugZap, ShieldCheck } from 'lucide-react';

function uniqueValues(values) {
    return [...new Set(values.filter(Boolean))];
}

export default function Create({ integrations, selectedIntegration, connectionTypes, authTypes, environments }) {
    const { flash } = usePage().props;
    const form = useForm({
        connection_integration_id: selectedIntegration?.id || integrations[0]?.id || '',
        name: selectedIntegration ? `${selectedIntegration.name} connection` : '',
        description: '',
        connection_type: 'application',
        auth_type: selectedIntegration?.auth_methods?.[0] || integrations[0]?.auth_methods?.[0] || 'none',
        environment: 'sandbox',
        provider_account_name: '',
        usage: ['knowledge_base'],
        configuration: { read_only: true },
        credential: {},
    });
    const currentIntegration = integrations.find((integration) => String(integration.id) === String(form.data.connection_integration_id)) || selectedIntegration || integrations[0];
    const supportedAuthTypes = currentIntegration?.auth_methods?.length ? currentIntegration.auth_methods : authTypes;
    const oauthPolicy = currentIntegration?.credential_schema?.oauth || null;
    const requiredScopes = uniqueValues((form.data.usage || []).flatMap((usage) => oauthPolicy?.required_scopes_by_usage?.[usage] || []));
    const recommendedScopes = uniqueValues([...(oauthPolicy?.default_scopes || []), ...requiredScopes]);
    const scopeDescriptions = oauthPolicy?.scope_descriptions || {};

    const submit = (event) => {
        event.preventDefault();
        form.post(route('tenant.admin.connections.store'));
    };

    const updateIntegration = (event) => {
        const integration = integrations.find((item) => String(item.id) === String(event.target.value));
        form.setData({
            ...form.data,
            connection_integration_id: event.target.value,
            auth_type: integration?.auth_methods?.[0] || 'none',
            name: integration ? `${integration.name} connection` : form.data.name,
        });
    };

    const startOAuth = () => {
        router.post(route('tenant.admin.connections.oauth.start'), {
            connection_integration_id: currentIntegration.id,
            scopes: recommendedScopes,
            redirect_path: '/connections/create',
        }, { preserveScroll: true });
    };

    return (
        <ConnectionsShell title="Add connection" description="Select an app, configure the tenant-owned connection, then test and discover resources from the detail screen.">
            <Head title="Add connection" />
            <form onSubmit={submit} className="grid gap-6 xl:grid-cols-[1fr_360px]">
                <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="md:col-span-2">
                            <span className="text-sm font-semibold text-slate-700">App</span>
                            <Select value={form.data.connection_integration_id} onChange={updateIntegration} className="mt-1">
                                {integrations.map((integration) => <option key={integration.id} value={integration.id}>{integration.name} - {integration.category}</option>)}
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
                            <Select value={form.data.auth_type} onChange={(event) => form.setData('auth_type', event.target.value)} className="mt-1">{supportedAuthTypes.map((item) => <option key={item} value={item}>{item.replaceAll('_', ' ')}</option>)}</Select>
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
                    {form.data.auth_type === 'oauth2' && oauthPolicy ? (
                        <div className="mt-6 rounded-lg border border-blue-100 bg-blue-50 p-4">
                            <div className="flex items-center gap-2 font-semibold text-blue-950">
                                <ShieldCheck className="h-4 w-4" /> OAuth scopes
                            </div>
                            <ul className="mt-3 space-y-2">
                                {recommendedScopes.map((scope) => (
                                    <li key={scope} className="rounded-md bg-white p-2">
                                        <p className="break-all font-mono text-xs text-slate-800">{scope}</p>
                                        <p className="mt-1 text-xs text-slate-500">{scopeDescriptions[scope] || scope}</p>
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-3 text-xs text-blue-800">Changing scopes later requires reauthorization.</p>
                            <button type="button" onClick={startOAuth} className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-600">
                                <KeyRound className="h-4 w-4" /> Prepare OAuth
                            </button>
                        </div>
                    ) : null}
                    {flash?.oauth_authorization ? (
                        <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-900">
                            <p className="font-semibold">OAuth authorization prepared</p>
                            <p className="mt-1">State expires in {flash.oauth_authorization.expires_in_minutes} minutes. Provider redirect wiring can use PKCE method {flash.oauth_authorization.code_challenge_method}.</p>
                        </div>
                    ) : null}
                </aside>
            </form>
        </ConnectionsShell>
    );
}
