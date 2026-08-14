import Pagination from '@/Components/Superadmin/Pagination';
import CustomersShell from '@/Components/Tenant/Customers/CustomersShell';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, Plus, SlidersHorizontal, UsersRound } from 'lucide-react';
import { useState } from 'react';

const tones = { active: 'brand', inactive: 'neutral', archived: 'warning' };

export default function Index({ companies, filters = {}, industries = [] }) {
    const permissions = usePage().props.auth?.permissions || [];
    const [search, setSearch] = useState(filters.search || '');

    const apply = (next = {}) => router.get(
        route('tenant.admin.customers.companies.index'),
        { ...filters, search, ...next },
        { preserveState: true, preserveScroll: true, replace: true },
    );

    return (
        <CustomersShell title="Companies" flush>
            <Head title="Companies" />

            <div className="border-b border-slate-200 bg-white px-3 py-3 sm:px-4">
                <div className="flex flex-col gap-2.5 lg:flex-row lg:items-center">
                    <form onSubmit={(event) => { event.preventDefault(); apply({ search: search.trim() }); }} className="min-w-0 flex-1">
                        <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); apply({ search: '' }); }} placeholder="Search company name or domain..." className="w-full" />
                    </form>
                    <div className="grid grid-cols-2 gap-2 lg:flex lg:shrink-0">
                        <Select value={filters.status || ''} onChange={(event) => apply({ status: event.target.value })} className="w-full lg:w-36"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></Select>
                        <Select value={filters.industry || ''} onChange={(event) => apply({ industry: event.target.value })} className="w-full lg:w-44"><option value="">All industries</option>{industries.map((industry) => <option key={industry}>{industry}</option>)}</Select>
                    </div>
                    {permissions.includes('companies.create') && <Button href={route('tenant.admin.customers.companies.create')} variant="brand" size="sm" icon={Plus} className="shrink-0">Add company</Button>}
                </div>
            </div>

            <div className="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/40 px-4 py-2.5">
                <div><p className="text-xs font-semibold text-slate-600">Companies</p><p className="mt-0.5 hidden text-[11px] text-slate-400 sm:block">Customer accounts and their relationships</p></div>
                <div className="flex items-center gap-1.5 text-xs text-slate-400"><SlidersHorizontal className="h-3.5 w-3.5" /><span>{companies.total ?? companies.data.length} companies</span></div>
            </div>

            <div className="min-h-0 flex-1">
                {companies.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/95 backdrop-blur">
                                <tr>{['Company', 'Industry', 'Account owner', 'Contacts', 'Status'].map((heading) => <th key={heading} className="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">{heading}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {companies.data.map((company) => (
                                    <tr key={company.public_uuid} className="group transition-colors hover:bg-slate-50/80">
                                        <td className="px-4 py-3.5">
                                            <Link href={route('tenant.admin.customers.companies.show', company.public_uuid)} className="flex min-w-0 items-center gap-3">
                                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-colors group-hover:bg-brand-50 group-hover:text-brand-700"><Building2 className="h-4 w-4" /></span>
                                                <span className="min-w-0"><span className="block max-w-64 truncate font-semibold text-slate-900 group-hover:text-brand-700">{company.name}</span><span className="block max-w-64 truncate text-xs text-slate-500">{company.domain || 'No domain configured'}</span></span>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3.5 text-slate-600">{company.industry || '—'}</td>
                                        <td className="px-4 py-3.5"><span className={company.account_owner ? 'font-medium text-slate-700' : 'text-slate-400'}>{company.account_owner?.name || 'Unassigned'}</span></td>
                                        <td className="px-4 py-3.5"><span className="inline-flex items-center gap-1.5 font-medium text-slate-700"><UsersRound className="h-3.5 w-3.5 text-slate-400" />{company.contacts_count}</span></td>
                                        <td className="px-4 py-3.5"><Badge tone={tones[company.status] || 'neutral'}>{company.status}</Badge></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="flex min-h-[430px] items-center justify-center p-6"><EmptyState icon={Building2} title="No companies found" description="Create a company or adjust the selected filters." action={permissions.includes('companies.create') && <Button href={route('tenant.admin.customers.companies.create')} variant="brand" icon={Plus}>Add company</Button>} /></div>
                )}
            </div>

            {companies.data.length > 0 && <div className="border-t border-slate-200 bg-slate-50/30 px-3 py-3 sm:px-4"><Pagination links={companies.links} /></div>}
        </CustomersShell>
    );
}
