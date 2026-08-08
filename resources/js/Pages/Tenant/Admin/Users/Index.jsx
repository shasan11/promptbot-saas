import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/Superadmin/Pagination';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Users as UsersIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

export default function Index({ users, roles = [] }) {
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState('');

    const filtered = useMemo(() => {
        return users.data.filter((user) => {
            const matchesSearch = !search || user.name.toLowerCase().includes(search.toLowerCase()) || user.email.toLowerCase().includes(search.toLowerCase());
            const matchesRole = !roleFilter || user.roles?.some((role) => String(role.id) === roleFilter);
            return matchesSearch && matchesRole;
        });
    }, [users.data, search, roleFilter]);

    return (
        <AuthenticatedLayout title="Users">
            <Head title="Users" />

            <div className="mb-5 flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-soft sm:flex-row sm:items-center">
                <SearchInput value={search} onChange={setSearch} onClear={() => setSearch('')} placeholder="Search this page's users" className="w-full max-w-xs" />
                <Select value={roleFilter} onChange={(event) => setRoleFilter(event.target.value)} className="w-48">
                    <option value="">All roles</option>
                    {roles.map((role) => <option key={role.id} value={role.id}>{role.label || role.name}</option>)}
                </Select>
                {(search || roleFilter) && <p className="text-xs text-slate-500 sm:ml-auto">Filtering this page only — {filtered.length} of {users.data.length} shown</p>}
            </div>

            <SectionCard title="Team members">
                {filtered.length ? (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Name</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Email</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Roles</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Created</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filtered.map((user) => (
                                    <tr key={user.id} className="hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2.5">
                                                <Avatar name={user.name} size="sm" />
                                                <span className="font-medium text-slate-900">{user.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{user.email}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles?.length ? user.roles.map((role) => <Badge key={role.id} tone="neutral">{role.label || role.name}</Badge>) : <span className="text-slate-400">No role</span>}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">{user.created_at ? new Date(user.created_at).toLocaleDateString() : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="p-5"><EmptyState icon={UsersIcon} title="No matching users" description="Try a different search term or role filter." /></div>
                )}
            </SectionCard>

            <Pagination links={users.links} />

            <SectionCard className="mt-6" title="Available roles" description="Roles configured for this workspace.">
                <div className="flex flex-wrap gap-2">
                    {roles.map((role) => <Badge key={role.id} tone="brand">{role.label || role.name}</Badge>)}
                </div>
            </SectionCard>
        </AuthenticatedLayout>
    );
}
