import DataTable from '@/Components/Superadmin/DataTable';
import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import CopyButton from '@/Components/UI/CopyButton';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import EmptyState from '@/Components/UI/EmptyState';
import FilterBar, { FilterChip } from '@/Components/UI/FilterBar';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, ExternalLink, Eye, Pause, Play, Plus } from 'lucide-react';
import { useState } from 'react';

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'provisioning', label: 'Provisioning' },
    { value: 'pending', label: 'Pending' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'failed', label: 'Failed' },
];

function tenantUrl(domain) {
    if (typeof window === 'undefined') return `//${domain}`;
    const port = window.location.port ? `:${window.location.port}` : '';
    return `${window.location.protocol}//${domain}${port}`;
}

export default function Index({ tenants, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const rows = tenants?.data || [];

    const applyFilters = (next = {}) => {
        const params = { search, status, ...next };
        router.get(route('superadmin.tenants.index'), params, { preserveState: true, preserveScroll: true });
    };

    const removeFilter = (key) => {
        if (key === 'search') setSearch('');
        if (key === 'status') setStatus('');
        applyFilters({ [key]: '' });
    };

    const columns = [
        {
            title: 'Company',
            dataIndex: 'company_name',
            render: (value, tenant) => (
                <div>
                    <Link href={route('superadmin.tenants.show', tenant.public_uuid || tenant.id)} className="font-semibold text-slate-900 hover:text-brand-700">
                        {value}
                    </Link>
                    <div className="mt-1 font-mono text-xs text-slate-500">{tenant.slug || tenant.id}</div>
                </div>
            ),
        },
        { title: 'Status', dataIndex: 'status', render: (status) => <StatusBadge status={status} /> },
        { title: 'Plan', dataIndex: ['plan', 'name'], render: (value) => value || '—' },
        {
            title: 'Primary domain',
            dataIndex: 'domains',
            render: (domains = []) => {
                const primary = domains.find((domain) => domain.is_primary) || domains[0];
                if (!primary) return '—';
                return (
                    <div className="flex items-center gap-1">
                        <span className="max-w-[180px] truncate font-mono text-xs text-slate-600" title={primary.domain}>{primary.domain}</span>
                        <CopyButton value={primary.domain} label="Copy domain" />
                        <a
                            href={tenantUrl(primary.domain)}
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={(event) => event.stopPropagation()}
                            aria-label="Open tenant domain"
                            className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            <ExternalLink className="h-3.5 w-3.5" />
                        </a>
                    </div>
                );
            },
        },
        {
            title: '',
            dataIndex: 'id',
            render: (_, tenant) => (
                <DropdownMenu
                    items={[
                        { label: 'View details', icon: Eye, onClick: () => router.visit(route('superadmin.tenants.show', tenant.public_uuid || tenant.id)) },
                        tenant.status === 'suspended'
                            ? { label: 'Reactivate tenant', icon: Play, onClick: () => router.post(route('superadmin.tenants.activate', tenant.id)) }
                            : { label: 'Suspend tenant', icon: Pause, onClick: () => router.post(route('superadmin.tenants.suspend', tenant.id)) },
                    ]}
                />
            ),
        },
    ];

    const activeFilters = [
        search && { key: 'search', label: `Search: “${search}”` },
        status && { key: 'status', label: `Status: ${statusOptions.find((option) => option.value === status)?.label}` },
    ].filter(Boolean);

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="Tenants"
                    subtitle="Create, inspect, and operate customer workspaces."
                    actions={<Button href={route('superadmin.tenants.create')} variant="brand" icon={Plus}>Create tenant</Button>}
                />
            )}
        >
            <Head title="Tenants" />

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
                <FilterBar>
                    <SearchInput
                        value={search}
                        onChange={setSearch}
                        onClear={() => { setSearch(''); applyFilters({ search: '' }); }}
                        placeholder="Search company or slug"
                        className="w-full max-w-xs"
                    />
                    <Select value={status} onChange={(event) => { setStatus(event.target.value); applyFilters({ status: event.target.value }); }} className="w-44">
                        {statusOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </Select>
                    <Button variant="secondary" size="sm" onClick={() => applyFilters()}>Apply</Button>
                </FilterBar>

                {activeFilters.length > 0 && (
                    <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                        {activeFilters.map((filter) => <FilterChip key={filter.key} label={filter.label} onRemove={() => removeFilter(filter.key)} />)}
                    </div>
                )}
            </div>

            <p className="mt-3 text-xs text-slate-500">{tenants?.total ?? rows.length} tenant{(tenants?.total ?? rows.length) === 1 ? '' : 's'} found</p>

            <div className="mt-3">
                {rows.length ? (
                    <>
                        <DataTable columns={columns} dataSource={rows} />
                        <Pagination links={tenants?.links} />
                    </>
                ) : (
                    <EmptyState
                        icon={Building2}
                        title={activeFilters.length ? 'No tenants match these filters' : 'No tenants yet'}
                        description={activeFilters.length ? 'Try a different search term or status.' : 'Provision your first tenant workspace to get started.'}
                        action={!activeFilters.length && <Button href={route('superadmin.tenants.create')} variant="brand" icon={Plus}>Create tenant</Button>}
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
