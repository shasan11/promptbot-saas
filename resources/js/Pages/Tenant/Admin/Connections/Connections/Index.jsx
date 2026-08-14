import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/Superadmin/Pagination';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import { HealthBadge, StatusBadge, humanize } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link, router } from '@inertiajs/react';
import {
    Activity, Cable, CheckCircle2, ChevronRight, Clock3, Database,
    Layers3, Plus, RefreshCcw, SearchX, Server, UserRound,
} from 'lucide-react';
import { useState } from 'react';

const summaryCards = [
    { key: 'total', label: 'Total connections', icon: Cable, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Active', icon: Activity, tone: 'bg-sky-50 text-sky-700' },
    { key: 'healthy', label: 'Healthy', icon: CheckCircle2, tone: 'bg-brand-50 text-brand-700' },
    { key: 'needsAttention', label: 'Needs attention', icon: RefreshCcw, tone: 'bg-amber-50 text-amber-700' },
];

function SummaryCard({ item, value }) {
    const Icon = item.icon;

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-soft">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold text-slate-500">{item.label}</p>
                    <p className="mt-1 text-2xl font-bold tracking-tight text-slate-900">{value ?? 0}</p>
                </div>
                <span className={`flex h-10 w-10 items-center justify-center rounded-xl ${item.tone}`}>
                    <Icon className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                </span>
            </div>
        </div>
    );
}

function MetaItem({ icon: Icon, children }) {
    return (
        <span className="inline-flex min-w-0 items-center gap-1.5 text-xs text-slate-500">
            <Icon className="h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />
            <span className="truncate">{children}</span>
        </span>
    );
}

function ConnectionRow({ connection }) {
    const providerName = connection.integration?.name || 'Custom connection';
    const providerInitial = providerName.charAt(0).toUpperCase();

    return (
        <Link
            href={route('tenant.admin.connections.show', connection.id)}
            className="group block border-b border-slate-100 p-4 transition-colors last:border-b-0 hover:bg-slate-50/80 sm:p-5"
        >
            <div className="flex items-start gap-3 sm:gap-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-gradient-to-br from-white to-slate-100 text-sm font-bold text-slate-700 shadow-sm">
                    {providerInitial}
                </span>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h3 className="truncate text-sm font-bold text-slate-900 transition-colors group-hover:text-brand-700 sm:text-[15px]">
                                    {connection.name}
                                </h3>
                                {connection.environment && (
                                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                        {humanize(connection.environment)}
                                    </span>
                                )}
                            </div>
                            <p className="mt-1 truncate text-xs text-slate-500">
                                <span className="font-semibold text-slate-700">{providerName}</span>
                                {connection.integration?.provider && ` by ${connection.integration.provider}`}
                                {connection.provider_account_name && ` · ${connection.provider_account_name}`}
                            </p>
                        </div>

                        <div className="flex shrink-0 flex-wrap items-center gap-2">
                            <StatusBadge value={connection.status} />
                            <HealthBadge value={connection.health_status} />
                        </div>
                    </div>

                    <div className="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        <MetaItem icon={Database}>{connection.data_sources_count ?? 0} data sources</MetaItem>
                        <MetaItem icon={Layers3}>{connection.resources_count ?? 0} resources</MetaItem>
                        <MetaItem icon={Server}>{humanize(connection.connection_type)}</MetaItem>
                        <MetaItem icon={UserRound}>{connection.owner?.name || 'No owner assigned'}</MetaItem>
                    </div>

                    <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                        <MetaItem icon={Clock3}>
                            {connection.last_checked_at
                                ? `Checked ${new Date(connection.last_checked_at).toLocaleString()}`
                                : 'Not checked yet'}
                        </MetaItem>
                        <span className="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 opacity-80 transition group-hover:opacity-100">
                            View connection <ChevronRight className="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                        </span>
                    </div>
                </div>
            </div>
        </Link>
    );
}

export default function Index({ connections, summary, filters, statusOptions, healthOptions, typeOptions }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [health, setHealth] = useState(filters.health_status || '');
    const [type, setType] = useState(filters.connection_type || '');
    const hasFilters = Boolean(filters.search || filters.status || filters.health_status || filters.connection_type);

    const apply = (next = {}) => router.get(route('tenant.admin.connections.index'), {
        search,
        status,
        health_status: health,
        connection_type: type,
        ...next,
    }, { preserveState: true, preserveScroll: true, replace: true });

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setHealth('');
        setType('');
        router.get(route('tenant.admin.connections.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <ConnectionsShell
            title="All connections"
            description="A clear view of every integration, its health, ownership, and connected data."
            actions={<Button href={route('tenant.admin.connections.create')} variant="brand" icon={Plus}>Add connection</Button>}
        >
            <Head title="All connections" />

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {summaryCards.map((item) => <SummaryCard key={item.key} item={item} value={summary?.[item.key]} />)}
            </div>

            <form
                onSubmit={(event) => { event.preventDefault(); apply(); }}
                className="mt-5 rounded-xl border border-slate-200 bg-white p-4 shadow-soft sm:p-5"
            >
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-sm font-bold text-slate-900">Find a connection</h2>
                        <p className="mt-0.5 text-xs text-slate-500">Search by connection or account, then narrow by operational state.</p>
                    </div>
                    {hasFilters && (
                        <button type="button" onClick={resetFilters} className="self-start text-xs font-semibold text-brand-700 hover:text-brand-800 sm:self-auto">
                            Reset filters
                        </button>
                    )}
                </div>

                <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1.5fr)_1fr_1fr_1fr_auto] xl:items-end">
                    <label className="block md:col-span-2 xl:col-span-1">
                        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Search</span>
                        <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); apply({ search: '' }); }} placeholder="Name or provider account" className="w-full" />
                    </label>
                    <label className="block">
                        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Status</span>
                        <Select value={status} onChange={(event) => { setStatus(event.target.value); apply({ status: event.target.value }); }} className="w-full">
                            <option value="">All statuses</option>
                            {statusOptions.map((item) => <option key={item} value={item}>{humanize(item)}</option>)}
                        </Select>
                    </label>
                    <label className="block">
                        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Health</span>
                        <Select value={health} onChange={(event) => { setHealth(event.target.value); apply({ health_status: event.target.value }); }} className="w-full">
                            <option value="">All health states</option>
                            {healthOptions.map((item) => <option key={item} value={item}>{humanize(item)}</option>)}
                        </Select>
                    </label>
                    <label className="block">
                        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Type</span>
                        <Select value={type} onChange={(event) => { setType(event.target.value); apply({ connection_type: event.target.value }); }} className="w-full">
                            <option value="">All types</option>
                            {typeOptions.map((item) => <option key={item} value={item}>{humanize(item)}</option>)}
                        </Select>
                    </label>
                    <Button type="submit" variant="secondary" className="w-full xl:w-auto">Apply</Button>
                </div>
            </form>

            <section className="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-soft">
                <div className="flex flex-col gap-1 border-b border-slate-200 bg-slate-50/70 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <h2 className="text-sm font-bold text-slate-900">Connection inventory</h2>
                        <p className="mt-0.5 text-xs text-slate-500">
                            {connections.total
                                ? `Showing ${connections.from}–${connections.to} of ${connections.total}`
                                : 'No connections to show'}
                        </p>
                    </div>
                    {hasFilters && <span className="text-xs font-semibold text-brand-700">Filtered results</span>}
                </div>

                {connections.data.length ? (
                    connections.data.map((connection) => <ConnectionRow key={connection.id} connection={connection} />)
                ) : (
                    <div className="p-5">
                        <EmptyState
                            icon={hasFilters ? SearchX : Cable}
                            title={hasFilters ? 'No connections match these filters' : 'No apps are connected yet'}
                            description={hasFilters ? 'Try a broader search or reset the filters to see every connection.' : 'Connect an app to discover resources and create data sources.'}
                            action={hasFilters
                                ? <Button variant="secondary" onClick={resetFilters}>Reset filters</Button>
                                : <Button href={route('tenant.admin.connections.create')} variant="brand" icon={Plus}>Connect an app</Button>}
                        />
                    </div>
                )}
            </section>

            {connections.data.length > 0 && <Pagination links={connections.links} />}
        </ConnectionsShell>
    );
}
