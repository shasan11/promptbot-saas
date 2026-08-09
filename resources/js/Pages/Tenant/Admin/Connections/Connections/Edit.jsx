import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';

export default function Edit({ connection, connectionTypes, environments }) {
    const form = useForm({
        name: connection.name || '',
        description: connection.description || '',
        connection_type: connection.connection_type || 'application',
        environment: connection.environment || 'production',
        provider_account_name: connection.provider_account_name || '',
        usage: connection.usage || [],
        configuration: connection.configuration || {},
    });

    const submit = (event) => {
        event.preventDefault();
        form.put(route('tenant.admin.connections.update', connection.id));
    };

    return (
        <ConnectionsShell title={`Edit ${connection.name}`} description={connection.integration?.name || 'Connection settings'}>
            <Head title={`Edit ${connection.name}`} />
            <form onSubmit={submit} className="max-w-3xl rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                <div className="grid gap-4 md:grid-cols-2">
                    <label>
                        <span className="text-sm font-semibold text-slate-700">Connection name</span>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="mt-1" required />
                        {form.errors.name && <p className="mt-1 text-xs text-rose-600">{form.errors.name}</p>}
                    </label>
                    <label>
                        <span className="text-sm font-semibold text-slate-700">Connected account</span>
                        <Input value={form.data.provider_account_name} onChange={(event) => form.setData('provider_account_name', event.target.value)} className="mt-1" />
                        {form.errors.provider_account_name && <p className="mt-1 text-xs text-rose-600">{form.errors.provider_account_name}</p>}
                    </label>
                    <label>
                        <span className="text-sm font-semibold text-slate-700">Connection type</span>
                        <Select value={form.data.connection_type} onChange={(event) => form.setData('connection_type', event.target.value)} className="mt-1">
                            {connectionTypes.map((item) => <option key={item} value={item}>{item.replaceAll('_', ' ')}</option>)}
                        </Select>
                    </label>
                    <label>
                        <span className="text-sm font-semibold text-slate-700">Environment</span>
                        <Select value={form.data.environment} onChange={(event) => form.setData('environment', event.target.value)} className="mt-1">
                            {environments.map((item) => <option key={item} value={item}>{item}</option>)}
                        </Select>
                    </label>
                    <label className="md:col-span-2">
                        <span className="text-sm font-semibold text-slate-700">Description</span>
                        <Textarea value={form.data.description || ''} onChange={(event) => form.setData('description', event.target.value)} className="mt-1" rows={4} />
                        {form.errors.description && <p className="mt-1 text-xs text-rose-600">{form.errors.description}</p>}
                    </label>
                </div>
                <div className="mt-6 flex justify-end gap-2">
                    <Button href={route('tenant.admin.connections.show', connection.id)} variant="secondary">Cancel</Button>
                    <Button type="submit" variant="brand" icon={Save} loading={form.processing}>Save</Button>
                </div>
            </form>
        </ConnectionsShell>
    );
}
