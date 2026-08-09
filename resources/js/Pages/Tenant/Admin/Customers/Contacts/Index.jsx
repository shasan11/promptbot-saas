import Pagination from '@/Components/Superadmin/Pagination';
import CustomersShell from '@/Components/Tenant/Customers/CustomersShell';
import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import FilterBar from '@/Components/UI/FilterBar';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, Plus, Upload, UsersRound } from 'lucide-react';
import { useState } from 'react';

const tones = { active: 'brand', vip: 'warning', blocked: 'danger', inactive: 'neutral' };

export default function Index({ contacts, filters, companies, owners, tags }) {
    const permissions = usePage().props.auth?.permissions || [];
    const [search, setSearch] = useState(filters.search || '');
    const apply = (next = {}) => router.get(route('tenant.admin.customers.contacts.index'), { ...filters, search, ...next }, { preserveState: true, preserveScroll: true });
    return <CustomersShell title="Contacts" description="Customer identities, ownership, contact points, and support history." actions={<>
        {permissions.includes('customers.export') && <Button href={route('tenant.admin.customers.exports.contacts')} variant="secondary" icon={Download}>Export</Button>}
        {permissions.includes('customers.import') && <Button href={route('tenant.admin.customers.imports.index')} variant="secondary" icon={Upload}>Import</Button>}
        {permissions.includes('customers.create') && <Button href={route('tenant.admin.customers.contacts.create')} variant="brand" icon={Plus}>Add contact</Button>}
    </>}>
        <Head title="Contacts" />
        <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-soft"><FilterBar>
            <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); apply({ search: '' }); }} placeholder="Search contacts" className="w-full max-w-xs" />
            <Select value={filters.status || ''} onChange={(e) => apply({ status: e.target.value })} className="w-40"><option value="">All statuses</option><option value="active">Active</option><option value="vip">VIP</option><option value="inactive">Inactive</option><option value="blocked">Blocked</option></Select>
            <Select value={filters.company || ''} onChange={(e) => apply({ company: e.target.value })} className="w-44"><option value="">All companies</option>{companies.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</Select>
            <Select value={filters.owner || ''} onChange={(e) => apply({ owner: e.target.value })} className="w-44"><option value="">All owners</option>{owners.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</Select>
            <Select value={filters.tag || ''} onChange={(e) => apply({ tag: e.target.value })} className="w-40"><option value="">All tags</option>{tags.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</Select>
            <Button variant="secondary" size="sm" onClick={() => apply()}>Apply</Button>
        </FilterBar></div>
        <div className="mt-4">{contacts.data.length ? <><div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-soft"><table className="min-w-full divide-y divide-slate-200 text-sm"><thead className="bg-slate-50"><tr>{['Contact', 'Company', 'Owner', 'Tags', 'Status', 'Last contacted'].map((x) => <th key={x} className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">{x}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">{contacts.data.map((contact) => <tr key={contact.public_uuid} className="hover:bg-slate-50"><td className="px-4 py-3"><Link href={route('tenant.admin.customers.contacts.show', contact.public_uuid)} className="flex items-center gap-2.5"><Avatar name={contact.display_name} size="sm" /><span><span className="block font-medium text-slate-900">{contact.display_name}</span><span className="block text-xs text-slate-500">{contact.email || contact.phone || 'No contact point'}</span></span></Link></td><td className="px-4 py-3 text-slate-600">{contact.company?.name || '—'}</td><td className="px-4 py-3 text-slate-600">{contact.owner?.name || 'Unassigned'}</td><td className="px-4 py-3"><div className="flex flex-wrap gap-1">{contact.tags.map((tag) => <Badge key={tag.public_uuid}>{tag.name}</Badge>)}</div></td><td className="px-4 py-3"><Badge tone={tones[contact.status]}>{contact.status}</Badge></td><td className="px-4 py-3 text-slate-500">{contact.last_contacted_at ? new Date(contact.last_contacted_at).toLocaleDateString() : 'Never'}</td></tr>)}</tbody></table></div><Pagination links={contacts.links} /></> : <EmptyState icon={UsersRound} title="No contacts found" description="Create or import customer contacts to begin building support history." action={permissions.includes('customers.create') && <Button href={route('tenant.admin.customers.contacts.create')} variant="brand" icon={Plus}>Add contact</Button>} />}</div>
    </CustomersShell>;
}
