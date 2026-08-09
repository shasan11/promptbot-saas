import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import Input from '@/Components/UI/Input';
import Pagination from '@/Components/Superadmin/Pagination';
import Textarea from '@/Components/UI/Textarea';
import { HealthBadge, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link, useForm } from '@inertiajs/react';
import { Database } from 'lucide-react';
import { useState } from 'react';

const csv = (value) => (Array.isArray(value) ? value.join(', ') : '');
const splitCsv = (value) => String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

function DatabaseConfigForm({ source }) {
    const config = source.database_config || {};
    const [filtersError, setFiltersError] = useState('');
    const form = useForm({
        schema_name: config.schema_name || '',
        table_name: config.table_name || source.resource?.name || source.name || '',
        primary_key: config.primary_key || '',
        incremental_column: config.incremental_column || '',
        allowed_columns_text: csv(config.allowed_columns),
        excluded_columns_text: csv(config.excluded_columns),
        filters_text: JSON.stringify(config.filters || [], null, 2),
        row_limit: config.row_limit || 10000,
        read_only: config.read_only ?? true,
        raw_sql: config.raw_sql || '',
    });

    const submit = (event) => {
        event.preventDefault();
        setFiltersError('');

        let filters = [];
        try {
            filters = form.data.filters_text.trim() ? JSON.parse(form.data.filters_text) : [];
        } catch {
            setFiltersError('Filters must be valid JSON.');
            return;
        }

        form.transform((data) => ({
            schema_name: data.schema_name || null,
            table_name: data.table_name,
            primary_key: data.primary_key || null,
            incremental_column: data.incremental_column || null,
            allowed_columns: splitCsv(data.allowed_columns_text),
            excluded_columns: splitCsv(data.excluded_columns_text),
            filters,
            row_limit: Number(data.row_limit),
            read_only: data.read_only,
            raw_sql: data.raw_sql || null,
        })).post(route('tenant.admin.connections.data-sources.database-config.store', source.id), {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="rounded-md border border-slate-200 bg-slate-50 p-4">
            <div className="grid gap-3 md:grid-cols-2">
                <Input value={form.data.schema_name} onChange={(event) => form.setData('schema_name', event.target.value)} placeholder="schema" />
                <Input value={form.data.table_name} onChange={(event) => form.setData('table_name', event.target.value)} placeholder="table_or_view" required />
                <Input value={form.data.primary_key} onChange={(event) => form.setData('primary_key', event.target.value)} placeholder="primary key" />
                <Input value={form.data.incremental_column} onChange={(event) => form.setData('incremental_column', event.target.value)} placeholder="incremental column" />
                <Input className="md:col-span-2" value={form.data.allowed_columns_text} onChange={(event) => form.setData('allowed_columns_text', event.target.value)} placeholder="id, name, email" required />
                <Input className="md:col-span-2" value={form.data.excluded_columns_text} onChange={(event) => form.setData('excluded_columns_text', event.target.value)} placeholder="password_hash, reset_token, api_key" />
                <Textarea className="md:col-span-2 font-mono text-xs" rows={3} value={form.data.filters_text} onChange={(event) => form.setData('filters_text', event.target.value)} />
                <Input type="number" min="1" max="100000" value={form.data.row_limit} onChange={(event) => form.setData('row_limit', event.target.value)} />
                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={form.data.read_only} onChange={(event) => form.setData('read_only', event.target.checked)} />
                    Read only
                </label>
                <Textarea className="md:col-span-2 font-mono text-xs" rows={3} value={form.data.raw_sql} onChange={(event) => form.setData('raw_sql', event.target.value)} placeholder="Optional SELECT-only query" />
            </div>
            {(filtersError || form.errors.allowed_columns || form.errors.table_name || form.errors.raw_sql) && (
                <p className="mt-2 text-xs text-rose-600">{filtersError || form.errors.allowed_columns || form.errors.table_name || form.errors.raw_sql}</p>
            )}
            <Button className="mt-3" size="sm" variant="brand" type="submit" loading={form.processing}>Save configuration</Button>
        </form>
    );
}

function SourceConfig({ source }) {
    const config = source.database_config;

    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-semibold text-slate-900">{source.name}</p>
                    <p className="mt-1 text-xs text-slate-500">{source.resource_type} | {source.sync_mode}</p>
                </div>
                <div className="flex flex-wrap gap-1.5">
                    <Badge tone={source.status === 'active' ? 'brand' : 'neutral'}>{source.status}</Badge>
                    {config?.read_only && <Badge tone="brand">Read only</Badge>}
                    {config?.raw_sql && <Badge tone="warning">Raw SQL</Badge>}
                </div>
            </div>
            {config && (
                <dl className="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
                    <div><dt className="font-semibold text-slate-500">Table</dt><dd>{[config.schema_name, config.table_name].filter(Boolean).join('.') || 'Not set'}</dd></div>
                    <div><dt className="font-semibold text-slate-500">Columns</dt><dd>{config.allowed_columns?.length || 0} allowed</dd></div>
                    <div><dt className="font-semibold text-slate-500">Row limit</dt><dd>{config.row_limit || 'Not set'}</dd></div>
                </dl>
            )}
            <div className="mt-4">
                <DatabaseConfigForm source={source} />
            </div>
        </div>
    );
}

export default function Index({ connections }) {
    return (
        <ConnectionsShell title="Databases" description="Read-only database connections, schema discovery, table sources, and controlled query access.">
            <Head title="Databases" />
            {connections.data.length ? (
                <>
                    <div className="space-y-5">
                        {connections.data.map((connection) => (
                            <article key={connection.id} className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <Link className="font-semibold text-brand-700" href={route('tenant.admin.connections.show', connection.id)}>{connection.name}</Link>
                                        <p className="mt-1 text-xs text-slate-500">{connection.configuration?.host || connection.provider_account_name || 'Host not recorded'}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2"><StatusBadge value={connection.status} /><HealthBadge value={connection.health_status} /></div>
                                </div>
                                <div className="mt-4 grid gap-4 xl:grid-cols-2">
                                    {connection.data_sources?.length ? connection.data_sources.map((source) => <SourceConfig key={source.id} source={source} />) : (
                                        <div className="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No database tables or views selected.</div>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                    <Pagination links={connections.links} />
                </>
            ) : <EmptyState icon={Database} title="No database connections yet" description="Add a read-only replica before configuring table or view data sources." />}
        </ConnectionsShell>
    );
}
