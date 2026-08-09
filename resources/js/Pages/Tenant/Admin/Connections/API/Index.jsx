import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import Input from '@/Components/UI/Input';
import Pagination from '@/Components/Superadmin/Pagination';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { HealthBadge, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Braces, Trash2 } from 'lucide-react';
import { useState } from 'react';

function OperationForm({ connection, methods, riskLevels }) {
    const [headersError, setHeadersError] = useState('');
    const form = useForm({
        key: '',
        name: '',
        method: 'GET',
        path: '/',
        headers_text: '{}',
        query_schema: {},
        body_schema: {},
        risk_level: 'low',
        enabled_for_ai: false,
        enabled_for_workflows: false,
        timeout_seconds: 30,
        max_response_kb: 512,
    });

    const submit = (event) => {
        event.preventDefault();
        setHeadersError('');

        let headers = {};
        try {
            headers = form.data.headers_text.trim() ? JSON.parse(form.data.headers_text) : {};
        } catch {
            setHeadersError('Headers must be valid JSON.');
            return;
        }

        form.transform((data) => ({
            key: data.key,
            name: data.name,
            method: data.method,
            path: data.path,
            headers,
            query_schema: data.query_schema,
            body_schema: data.body_schema,
            risk_level: data.risk_level,
            enabled_for_ai: data.enabled_for_ai,
            enabled_for_workflows: data.enabled_for_workflows,
            timeout_seconds: Number(data.timeout_seconds),
            max_response_kb: Number(data.max_response_kb),
        })).post(route('tenant.admin.connections.api-operations.store', connection.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="rounded-md border border-slate-200 bg-slate-50 p-4">
            <div className="grid gap-3 md:grid-cols-2">
                <Input value={form.data.key} onChange={(event) => form.setData('key', event.target.value)} placeholder="get_customer" required />
                <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Get customer" required />
                <Select value={form.data.method} onChange={(event) => form.setData('method', event.target.value)}>
                    {methods.map((method) => <option key={method} value={method}>{method}</option>)}
                </Select>
                <Select value={form.data.risk_level} onChange={(event) => form.setData('risk_level', event.target.value)}>
                    {riskLevels.map((risk) => <option key={risk} value={risk}>{risk}</option>)}
                </Select>
                <Input className="md:col-span-2" value={form.data.path} onChange={(event) => form.setData('path', event.target.value)} placeholder="/customers/{customer_id}" required />
                <Textarea className="md:col-span-2 font-mono text-xs" rows={3} value={form.data.headers_text} onChange={(event) => form.setData('headers_text', event.target.value)} />
                <Input type="number" min="1" max="120" value={form.data.timeout_seconds} onChange={(event) => form.setData('timeout_seconds', event.target.value)} />
                <Input type="number" min="1" max="5120" value={form.data.max_response_kb} onChange={(event) => form.setData('max_response_kb', event.target.value)} />
            </div>
            <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-700">
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.enabled_for_ai} onChange={(event) => form.setData('enabled_for_ai', event.target.checked)} /> AI enabled</label>
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.enabled_for_workflows} onChange={(event) => form.setData('enabled_for_workflows', event.target.checked)} /> Workflow enabled</label>
            </div>
            {(headersError || form.errors.path || form.errors.key) && <p className="mt-2 text-xs text-rose-600">{headersError || form.errors.path || form.errors.key}</p>}
            <Button className="mt-3" type="submit" size="sm" variant="brand" loading={form.processing}>Save operation</Button>
        </form>
    );
}

function OperationList({ operations }) {
    return (
        <ul className="divide-y divide-slate-100 rounded-md border border-slate-200 text-sm">
            {operations.length ? operations.map((operation) => (
                <li key={operation.id} className="flex items-start justify-between gap-3 px-3 py-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-semibold text-slate-900">{operation.name}</span>
                            <Badge tone={operation.method === 'GET' ? 'brand' : 'warning'}>{operation.method}</Badge>
                            <Badge tone={operation.status === 'active' ? 'brand' : 'neutral'}>{operation.status}</Badge>
                        </div>
                        <p className="mt-1 font-mono text-xs text-slate-500">{operation.path}</p>
                        <p className="mt-1 text-xs text-slate-500">{operation.risk_level} risk{operation.enabled_for_ai ? ' | AI' : ''}{operation.enabled_for_workflows ? ' | workflows' : ''}</p>
                    </div>
                    {operation.status === 'active' && (
                        <Button size="sm" variant="ghost" icon={Trash2} onClick={() => router.delete(route('tenant.admin.connections.api-operations.destroy', operation.id), { preserveScroll: true })}>Disable</Button>
                    )}
                </li>
            )) : <li className="px-3 py-4 text-sm text-slate-500">No operations configured.</li>}
        </ul>
    );
}

export default function Index({ connections, methods, riskLevels }) {
    return (
        <ConnectionsShell title="API connections" description="Custom REST APIs with safe base URLs, scoped operations, and controlled AI/workflow exposure.">
            <Head title="API connections" />
            {connections.data.length ? (
                <>
                    <div className="space-y-4">
                        {connections.data.map((connection) => (
                            <article key={connection.id} className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <Link href={route('tenant.admin.connections.show', connection.id)} className="font-semibold text-brand-700">{connection.name}</Link>
                                        <p className="mt-1 text-xs text-slate-500">{connection.configuration?.base_url || 'No base URL configured'}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2"><StatusBadge value={connection.status} /><HealthBadge value={connection.health_status} /></div>
                                </div>
                                <div className="mt-5 grid gap-4 xl:grid-cols-[1fr_360px]">
                                    <OperationList operations={connection.api_operations || []} />
                                    <OperationForm connection={connection} methods={methods} riskLevels={riskLevels} />
                                </div>
                            </article>
                        ))}
                    </div>
                    <Pagination links={connections.links} />
                </>
            ) : <EmptyState icon={Braces} title="No API connections yet" description="Create a custom API connection to expose approved endpoints to workflows and AI actions." />}
        </ConnectionsShell>
    );
}
