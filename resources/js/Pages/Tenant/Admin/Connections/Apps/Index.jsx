import Button from '@/Components/UI/Button';
import { CapabilityList } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import Pagination from '@/Components/Superadmin/Pagination';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import { Head, Link, router } from '@inertiajs/react';
import { PlugZap } from 'lucide-react';
import { useState } from 'react';

export default function Index({ integrations, categories, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [category, setCategory] = useState(filters.category || '');
    const applyFilters = (next = {}) => router.get(route('tenant.admin.connections.apps.index'), { search, category, ...next }, { preserveState: true });

    return (
        <ConnectionsShell title="App catalog" description="Browse supported integrations by category, authentication model, and capabilities.">
            <Head title="App catalog" />
            <div className="mb-4 flex flex-wrap gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
                <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); applyFilters({ search: '' }); }} placeholder="Search integrations" className="w-full max-w-sm" />
                <Select value={category} onChange={(event) => { setCategory(event.target.value); applyFilters({ category: event.target.value }); }} className="w-52">
                    <option value="">All categories</option>
                    {categories.map((item) => <option key={item} value={item}>{item}</option>)}
                </Select>
                <Button variant="secondary" onClick={() => applyFilters()}>Apply</Button>
            </div>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {integrations.data.map((integration) => (
                    <Link key={integration.id} href={route('tenant.admin.connections.apps.show', integration.key)} className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft hover:border-brand-200 hover:shadow-soft-lg">
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <span className="flex h-10 w-10 items-center justify-center rounded-md bg-slate-100 text-slate-700"><PlugZap className="h-5 w-5" /></span>
                                <div>
                                    <p className="font-semibold text-slate-900">{integration.name}</p>
                                    <p className="text-xs text-slate-500">{integration.category} · {integration.auth_methods.join(', ')}</p>
                                </div>
                            </div>
                            <span className="text-xs font-semibold text-brand-700">{integration.connections_count ? 'Manage connections' : 'Connect'}</span>
                        </div>
                        <p className="mt-4 min-h-10 text-sm text-slate-600">{integration.description}</p>
                        <div className="mt-4"><CapabilityList capabilities={integration.capabilities || []} /></div>
                    </Link>
                ))}
            </div>
            <Pagination links={integrations.links} />
        </ConnectionsShell>
    );
}
