import Pagination from '@/Components/Superadmin/Pagination';
import CustomersShell from '@/Components/Tenant/Customers/CustomersShell';
import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, Plus, SlidersHorizontal, Upload, UsersRound } from 'lucide-react';
import { useState } from 'react';

const tones = { active: 'brand', vip: 'warning', blocked: 'danger', inactive: 'neutral' };

export default function Index({ contacts, filters = {}, companies = [], owners = [], tags = [] }) {
    const permissions = usePage().props.auth?.permissions || [];
    const [search, setSearch] = useState(filters.search || '');

    const apply = (next = {}) => router.get(
        route('tenant.admin.customers.contacts.index'),
        { ...filters, search, ...next },
        { preserveState: true, preserveScroll: true, replace: true },
    );

    return (
        <CustomersShell title="Contacts" flush>
            <Head title="Contacts" />

            <div className="border-b border-slate-200 bg-white px-3 py-3 sm:px-4">
                <div className="flex flex-col gap-2.5 xl:flex-row xl:items-center">
                    <form onSubmit={(event) => { event.preventDefault(); apply({ search: search.trim() }); }} className="min-w-0 flex-1">
                        <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); apply({ search: '' }); }} placeholder="Search name, email, phone or ID..." className="w-full" />
                    </form>

                    <div className="grid grid-cols-2 gap-2 md:grid-cols-4 xl:flex xl:shrink-0">
                        <Select value={filters.status || ''} onChange={(event) => apply({ status: event.target.value })} className="w-full xl:w-32"><option value="">All statuses</option><option value="active">Active</option><option value="vip">VIP</option><option value="inactive">Inactive</option><option value="blocked">Blocked</option></Select>
                        <Select value={filters.company || ''} onChange={(event) => apply({ company: event.target.value })} className="w-full xl:w-40"><option value="">All companies</option>{companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</Select>
                        <Select value={filters.owner || ''} onChange={(event) => apply({ owner: event.target.value })} className="w-full xl:w-40"><option value="">All owners</option>{owners.map((owner) => <option key={owner.id} value={owner.id}>{owner.name}</option>)}</Select>
                        <Select value={filters.tag || ''} onChange={(event) => apply({ tag: event.target.value })} className="w-full xl:w-32"><option value="">All tags</option>{tags.map((tag) => <option key={tag.id} value={tag.id}>{tag.name}</option>)}</Select>
                    </div>

                    <div className="flex shrink-0 flex-wrap gap-2">
                        {permissions.includes('customers.export') && <Button href={route('tenant.admin.customers.exports.contacts')} variant="secondary" size="sm" icon={Download}>Export</Button>}
                        {permissions.includes('customers.import') && <Button href={route('tenant.admin.customers.imports.index')} variant="secondary" size="sm" icon={Upload}>Import</Button>}
                        {permissions.includes('customers.create') && <Button href={route('tenant.admin.customers.contacts.create')} variant="brand" size="sm" icon={Plus}>Add contact</Button>}
                    </div>
                </div>
            </div>

            <div className="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/40 px-4 py-2.5">
                <div><p className="text-xs font-semibold text-slate-600">Contacts</p><p className="mt-0.5 hidden text-[11px] text-slate-400 sm:block">Customer identities and support relationships</p></div>
                <div className="flex items-center gap-1.5 text-xs text-slate-400"><SlidersHorizontal className="h-3.5 w-3.5" /><span>{contacts.total ?? contacts.data.length} contacts</span></div>
            </div>

            <div className="min-h-0 flex-1">
                {contacts.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[880px] text-sm">
                            <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/95 backdrop-blur">
                                <tr>{['Contact', 'Company', 'Owner', 'Tags', 'Status', 'Last contacted'].map((heading) => <th key={heading} className="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">{heading}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {contacts.data.map((contact) => (
                                    <tr key={contact.public_uuid} className="group transition-colors hover:bg-slate-50/80">
                                        <td className="px-4 py-3.5">
                                            <Link href={route('tenant.admin.customers.contacts.show', contact.public_uuid)} className="flex min-w-0 items-center gap-2.5">
                                                <Avatar name={contact.display_name} size="sm" />
                                                <span className="min-w-0"><span className="block max-w-56 truncate font-semibold text-slate-900 group-hover:text-brand-700">{contact.display_name}</span><span className="block max-w-56 truncate text-xs text-slate-500">{contact.email || contact.phone || 'No contact point'}</span></span>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3.5"><span className="block max-w-44 truncate font-medium text-slate-700">{contact.company?.name || '—'}</span></td>
                                        <td className="px-4 py-3.5 text-slate-600">{contact.owner?.name || <span className="text-slate-400">Unassigned</span>}</td>
                                        <td className="px-4 py-3.5"><div className="flex max-w-48 flex-wrap gap-1">{contact.tags.slice(0, 2).map((tag) => <Badge key={tag.public_uuid}>{tag.name}</Badge>)}{contact.tags.length > 2 && <Badge>+{contact.tags.length - 2}</Badge>}{contact.tags.length === 0 && <span className="text-xs text-slate-400">No tags</span>}</div></td>
                                        <td className="px-4 py-3.5"><Badge tone={tones[contact.status] || 'neutral'}>{contact.status}</Badge></td>
                                        <td className="whitespace-nowrap px-4 py-3.5 text-xs font-medium text-slate-500">{contact.last_contacted_at ? new Date(contact.last_contacted_at).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) : 'Never'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="flex min-h-[430px] items-center justify-center p-6"><EmptyState icon={UsersRound} title="No contacts found" description="Create or import contacts, or adjust the selected filters." action={permissions.includes('customers.create') && <Button href={route('tenant.admin.customers.contacts.create')} variant="brand" icon={Plus}>Add contact</Button>} /></div>
                )}
            </div>

            {contacts.data.length > 0 && <div className="border-t border-slate-200 bg-slate-50/30 px-3 py-3 sm:px-4"><Pagination links={contacts.links} /></div>}
        </CustomersShell>
    );
}
