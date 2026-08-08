import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import EmptyState from '@/Components/UI/EmptyState';
import FilterBar from '@/Components/UI/FilterBar';
import Pagination from '@/Components/Superadmin/Pagination';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Plus, ShieldOff, ShieldCheck, UserCog, Users as UsersIcon } from 'lucide-react';
import { useState } from 'react';

const statusTone = { active: 'brand', invited: 'info', suspended: 'danger', deactivated: 'neutral', expired: 'neutral' };

export default function Index({ users, roles, departments, filters }) {
    const { auth } = usePage().props;
    const canCreate = auth?.permissions?.includes('users.create');
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [role, setRole] = useState(filters.role || '');
    const [department, setDepartment] = useState(filters.department || '');

    const applyFilters = (next = {}) => {
        router.get(route('tenant.admin.administration.users.index'), { search, status, role, department, ...next }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdministrationShell
            title="Users"
            description="Manage every account with access to this workspace."
            actions={canCreate && <Button href={route('tenant.admin.administration.users.create')} variant="brand" icon={Plus}>Add user</Button>}
        >
            <Head title="Users" />

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
                <FilterBar>
                    <SearchInput value={search} onChange={setSearch} onClear={() => { setSearch(''); applyFilters({ search: '' }); }} placeholder="Search name or email" className="w-full max-w-xs" />
                    <Select value={status} onChange={(event) => { setStatus(event.target.value); applyFilters({ status: event.target.value }); }} className="w-40">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="invited">Invited</option>
                        <option value="suspended">Suspended</option>
                        <option value="deactivated">Deactivated</option>
                    </Select>
                    <Select value={role} onChange={(event) => { setRole(event.target.value); applyFilters({ role: event.target.value }); }} className="w-44">
                        <option value="">All roles</option>
                        {roles.map((r) => <option key={r.id} value={r.id}>{r.label || r.name}</option>)}
                    </Select>
                    <Select value={department} onChange={(event) => { setDepartment(event.target.value); applyFilters({ department: event.target.value }); }} className="w-44">
                        <option value="">All departments</option>
                        {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                    </Select>
                    <Button variant="secondary" size="sm" onClick={() => applyFilters()}>Apply</Button>
                </FilterBar>
            </div>

            <div className="mt-4">
                {users.data.length ? (
                    <>
                        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">User</th>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Department</th>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Roles</th>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Status</th>
                                            <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Last active</th>
                                            <th className="px-4 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {users.data.map((user) => (
                                            <tr key={user.id} className="hover:bg-slate-50">
                                                <td className="px-4 py-3">
                                                    <Link href={route('tenant.admin.administration.users.show', user.id)} className="flex items-center gap-2.5">
                                                        <Avatar name={user.name} size="sm" />
                                                        <div>
                                                            <div className="font-medium text-slate-900">{user.name}</div>
                                                            <div className="text-xs text-slate-500">{user.email}</div>
                                                        </div>
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{user.department?.name || '—'}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-wrap gap-1">
                                                        {user.roles?.length ? user.roles.map((r) => <Badge key={r.id} tone="neutral">{r.label || r.name}</Badge>) : <span className="text-slate-400">No role</span>}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3"><Badge tone={statusTone[user.status] || 'neutral'}>{user.status}</Badge></td>
                                                <td className="px-4 py-3 text-slate-500">{user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never'}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <DropdownMenu
                                                        items={[
                                                            { label: 'View user', icon: Eye, onClick: () => router.visit(route('tenant.admin.administration.users.show', user.id)) },
                                                            { label: 'Manage roles', icon: UserCog, onClick: () => router.visit(route('tenant.admin.administration.users.show', user.id) + '#access') },
                                                            { divider: true },
                                                            user.status === 'suspended'
                                                                ? { label: 'Reactivate', icon: ShieldCheck, onClick: () => router.post(route('tenant.admin.administration.users.activate', user.id)) }
                                                                : { label: 'Suspend', icon: ShieldOff, danger: true, onClick: () => router.post(route('tenant.admin.administration.users.suspend', user.id)) },
                                                        ]}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <Pagination links={users.links} />
                    </>
                ) : (
                    <EmptyState icon={UsersIcon} title="No team members yet" description="Invite your first team member and assign their access." action={canCreate && <Button href={route('tenant.admin.administration.users.create')} variant="brand" icon={Plus}>Add user</Button>} />
                )}
            </div>
        </AdministrationShell>
    );
}
