import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import Input from '@/Components/UI/Input';
import Pagination from '@/Components/Superadmin/Pagination';
import Select from '@/Components/UI/Select';
import { HealthBadge, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, router, useForm } from '@inertiajs/react';
import { FolderLock, ShieldCheck, Trash2 } from 'lucide-react';

const listValue = (value) => (Array.isArray(value) ? value.join(', ') : '');
const splitList = (value) => String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

function CheckboxGroup({ items, value, onChange, empty = 'No enabled items' }) {
    const toggle = (key) => {
        onChange(value.includes(key) ? value.filter((item) => item !== key) : [...value, key]);
    };

    if (!items.length) {
        return <p className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">{empty}</p>;
    }

    return (
        <div className="grid gap-2 sm:grid-cols-2">
            {items.map((item) => (
                <label key={item.key} className="flex items-start gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs text-slate-700">
                    <input type="checkbox" className="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" checked={value.includes(item.key)} onChange={() => toggle(item.key)} />
                    <span>
                        <span className="font-semibold text-slate-800">{item.name || item.key}</span>
                        {item.risk_level && <span className="ml-1 text-slate-500">{item.risk_level}</span>}
                    </span>
                </label>
            ))}
        </div>
    );
}

function AgentGrantForm({ connection }) {
    const actions = connection.actions.filter((action) => action.status === 'active' && action.enabled_for_ai);
    const form = useForm({
        agent_key: '',
        allowed_actions: [],
        allowed_resources_text: '',
        read_only: true,
        approval_required: true,
        rate_limit_per_hour: '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({
            agent_key: data.agent_key,
            allowed_actions: data.allowed_actions,
            allowed_resources: splitList(data.allowed_resources_text),
            read_only: data.read_only,
            approval_required: data.approval_required,
            rate_limit_per_hour: data.rate_limit_per_hour || null,
        })).post(route('tenant.admin.connections.permissions.agents.store', connection.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <Input value={form.data.agent_key} onChange={(event) => form.setData('agent_key', event.target.value)} placeholder="sales-agent" required />
            <CheckboxGroup items={actions} value={form.data.allowed_actions} onChange={(value) => form.setData('allowed_actions', value)} />
            <Input value={form.data.allowed_resources_text} onChange={(event) => form.setData('allowed_resources_text', event.target.value)} placeholder="resource keys, comma separated" />
            <div className="flex flex-wrap gap-4 text-xs text-slate-700">
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.read_only} onChange={(event) => form.setData('read_only', event.target.checked)} /> Read only</label>
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.approval_required} onChange={(event) => form.setData('approval_required', event.target.checked)} /> Approval required</label>
                <Input className="h-8 w-36" type="number" min="1" max="10000" value={form.data.rate_limit_per_hour} onChange={(event) => form.setData('rate_limit_per_hour', event.target.value)} placeholder="Hourly limit" />
            </div>
            {form.errors.allowed_actions && <p className="text-xs text-rose-600">{form.errors.allowed_actions}</p>}
            <Button type="submit" size="sm" variant="brand" loading={form.processing} disabled={!actions.length}>Save agent grant</Button>
        </form>
    );
}

function WorkflowGrantForm({ connection }) {
    const actions = connection.actions.filter((action) => action.status === 'active' && action.enabled_for_workflows);
    const triggers = connection.triggers.filter((trigger) => trigger.status === 'active');
    const form = useForm({
        workflow_key: '',
        allowed_actions: [],
        allowed_triggers: [],
        approval_required: true,
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(route('tenant.admin.connections.permissions.workflows.store', connection.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <Input value={form.data.workflow_key} onChange={(event) => form.setData('workflow_key', event.target.value)} placeholder="lead-enrichment" required />
            <CheckboxGroup items={actions} value={form.data.allowed_actions} onChange={(value) => form.setData('allowed_actions', value)} />
            <CheckboxGroup items={triggers} value={form.data.allowed_triggers} onChange={(value) => form.setData('allowed_triggers', value)} empty="No active triggers" />
            <label className="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" checked={form.data.approval_required} onChange={(event) => form.setData('approval_required', event.target.checked)} /> Approval required</label>
            {form.errors.allowed_actions && <p className="text-xs text-rose-600">{form.errors.allowed_actions}</p>}
            <Button type="submit" size="sm" variant="brand" loading={form.processing} disabled={!actions.length}>Save workflow grant</Button>
        </form>
    );
}

function AccessGrantForm({ connection, accessCapabilities }) {
    const form = useForm({
        subject_type: 'workspace',
        subject_id: '',
        capabilities: ['resources.view'],
        expires_at: '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(route('tenant.admin.connections.permissions.access-grants.store', connection.id), {
            preserveScroll: true,
            onSuccess: () => form.reset('subject_id', 'expires_at'),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <div className="grid gap-2 sm:grid-cols-2">
                <Select value={form.data.subject_type} onChange={(event) => form.setData('subject_type', event.target.value)}>
                    {['workspace', 'user', 'team', 'role'].map((item) => <option key={item} value={item}>{item}</option>)}
                </Select>
                <Input type="number" min="1" value={form.data.subject_id} onChange={(event) => form.setData('subject_id', event.target.value)} placeholder="Subject ID" disabled={form.data.subject_type === 'workspace'} />
            </div>
            <CheckboxGroup items={accessCapabilities.map((capability) => ({ key: capability, name: capability }))} value={form.data.capabilities} onChange={(value) => form.setData('capabilities', value)} />
            <Input type="datetime-local" value={form.data.expires_at} onChange={(event) => form.setData('expires_at', event.target.value)} />
            {form.errors.subject_id && <p className="text-xs text-rose-600">{form.errors.subject_id}</p>}
            <Button type="submit" size="sm" variant="brand" loading={form.processing}>Save access grant</Button>
        </form>
    );
}

function ResourceGrantForm({ resource, accessCapabilities }) {
    const form = useForm({
        subject_type: 'workspace',
        subject_id: '',
        capabilities: ['resources.view', 'resources.sync'],
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(route('tenant.admin.connections.permissions.resources.store', resource.id), {
            preserveScroll: true,
            onSuccess: () => form.reset('subject_id'),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-2 lg:grid-cols-[1fr_1fr_2fr_auto]">
            <Select value={form.data.subject_type} onChange={(event) => form.setData('subject_type', event.target.value)}>
                {['workspace', 'user', 'team', 'role'].map((item) => <option key={item} value={item}>{item}</option>)}
            </Select>
            <Input type="number" min="1" value={form.data.subject_id} onChange={(event) => form.setData('subject_id', event.target.value)} placeholder="Subject ID" disabled={form.data.subject_type === 'workspace'} />
            <CheckboxGroup items={accessCapabilities.map((capability) => ({ key: capability, name: capability }))} value={form.data.capabilities} onChange={(value) => form.setData('capabilities', value)} />
            <Button type="submit" size="sm" variant="brand" loading={form.processing}>Save</Button>
            {form.errors.subject_id && <p className="text-xs text-rose-600 lg:col-span-4">{form.errors.subject_id}</p>}
        </form>
    );
}

function GrantList({ title, items, type }) {
    const routeName = {
        agent: 'tenant.admin.connections.permissions.agents.destroy',
        workflow: 'tenant.admin.connections.permissions.workflows.destroy',
        access: 'tenant.admin.connections.permissions.access-grants.destroy',
    }[type];

    return (
        <div>
            <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500">{title}</h3>
            <ul className="mt-2 divide-y divide-slate-100 rounded-md border border-slate-200">
                {items.length ? items.map((item) => (
                    <li key={item.id} className="flex items-start justify-between gap-3 px-3 py-2 text-xs">
                        <div>
                            <p className="font-semibold text-slate-800">{item.agent_key || item.workflow_key || `${item.subject_type}${item.subject_id ? ` #${item.subject_id}` : ''}`}</p>
                            <p className="mt-1 text-slate-500">{listValue(item.allowed_actions || item.capabilities)}{item.allowed_triggers?.length ? ` | triggers: ${listValue(item.allowed_triggers)}` : ''}</p>
                            {(item.read_only || item.approval_required) && (
                                <div className="mt-1 flex gap-1">
                                    {item.read_only && <Badge tone="neutral">Read only</Badge>}
                                    {item.approval_required && <Badge tone="warning">Approval</Badge>}
                                </div>
                            )}
                        </div>
                        <Button size="sm" variant="ghost" icon={Trash2} onClick={() => router.delete(route(routeName, item.id), { preserveScroll: true })}>Remove</Button>
                    </li>
                )) : <li className="px-3 py-3 text-xs text-slate-500">No grants</li>}
            </ul>
        </div>
    );
}

function ResourceGrantList({ resource }) {
    const grants = resource.permissions || [];

    return (
        <ul className="mt-3 divide-y divide-slate-100 rounded-md border border-slate-200">
            {grants.length ? grants.map((grant) => (
                <li key={grant.id} className="flex items-start justify-between gap-3 px-3 py-2 text-xs">
                    <div>
                        <p className="font-semibold text-slate-800">{grant.subject_type}{grant.subject_id ? ` #${grant.subject_id}` : ''}</p>
                        <p className="mt-1 text-slate-500">{listValue(grant.capabilities)}</p>
                    </div>
                    <Button size="sm" variant="ghost" icon={Trash2} onClick={() => router.delete(route('tenant.admin.connections.permissions.resources.destroy', grant.id), { preserveScroll: true })}>Remove</Button>
                </li>
            )) : <li className="px-3 py-3 text-xs text-slate-500">No resource grants</li>}
        </ul>
    );
}

function ResourcePermissions({ connection, accessCapabilities }) {
    const resources = (connection.resources || []).filter((resource) => resource.selected_at && resource.status === 'available').slice(0, 6);

    return (
        <div className="border-t border-slate-200 pt-5">
            <div className="mb-3 flex items-center gap-2">
                <FolderLock className="h-4 w-4 text-brand-600" />
                <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500">Resource grants</h3>
            </div>
            {resources.length ? (
                <div className="divide-y divide-slate-100">
                    {resources.map((resource) => (
                        <section key={resource.id} className="py-4 first:pt-0 last:pb-0">
                            <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="text-sm font-semibold text-slate-900">{resource.name}</p>
                                    <p className="mt-1 text-xs text-slate-500">{resource.path || resource.external_id}</p>
                                </div>
                                <Badge tone="neutral">{resource.resource_type}</Badge>
                            </div>
                            <ResourceGrantForm resource={resource} accessCapabilities={accessCapabilities} />
                            <ResourceGrantList resource={resource} />
                        </section>
                    ))}
                </div>
            ) : <p className="rounded-md border border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-500">No selected resources</p>}
        </div>
    );
}

function ConnectionPermissions({ connection, accessCapabilities }) {
    return (
        <article className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-slate-900">{connection.name}</h2>
                    <p className="mt-1 text-xs text-slate-500">{connection.integration?.name || 'Custom connection'}</p>
                </div>
                <div className="flex flex-wrap gap-1.5">
                    <StatusBadge value={connection.status} />
                    <HealthBadge value={connection.health_status} />
                    <Badge tone="info">{connection.agent_access_count} agents</Badge>
                    <Badge tone="info">{connection.workflow_access_count} workflows</Badge>
                </div>
            </div>
            <div className="mt-5 grid gap-5 xl:grid-cols-3">
                <div className="space-y-5">
                    <AgentGrantForm connection={connection} />
                    <GrantList title="AI agent grants" items={connection.agent_access || []} type="agent" />
                </div>
                <div className="space-y-5">
                    <WorkflowGrantForm connection={connection} />
                    <GrantList title="Workflow grants" items={connection.workflow_access || []} type="workflow" />
                </div>
                <div className="space-y-5">
                    <AccessGrantForm connection={connection} accessCapabilities={accessCapabilities} />
                    <GrantList title="Access grants" items={connection.access_grants || []} type="access" />
                </div>
            </div>
            <div className="mt-5">
                <ResourcePermissions connection={connection} accessCapabilities={accessCapabilities} />
            </div>
        </article>
    );
}

export default function Index({ connections, accessCapabilities }) {
    return (
        <ConnectionsShell title="Connection permissions" description="Control which agents, workflows, and tenant subjects may use connection capabilities.">
            <Head title="Connection permissions" />
            {connections.data.length ? (
                <>
                    <div className="space-y-4">
                        {connections.data.map((connection) => <ConnectionPermissions key={connection.id} connection={connection} accessCapabilities={accessCapabilities} />)}
                    </div>
                    <Pagination links={connections.links} />
                </>
            ) : <EmptyState icon={ShieldCheck} title="No connections available" description="Permissions can be configured after a connection is created." />}
        </ConnectionsShell>
    );
}
