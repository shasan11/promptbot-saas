import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import DangerConfirmDialog from '@/Components/UI/DangerConfirmDialog';
import DescriptionList from '@/Components/UI/DescriptionList';
import Tabs from '@/Components/UI/Tabs';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const statusTone = { active: 'brand', invited: 'info', suspended: 'danger', deactivated: 'neutral', expired: 'neutral' };

export default function Show({ user, permissions, allRoles, canManageRoles, canSuspend, canDelete }) {
    const [tab, setTab] = useState('overview');
    const [dangerAction, setDangerAction] = useState(null);
    const rolesForm = useForm({ roles: user.roles.map((r) => r.id) });

    const toggleRole = (id) => {
        rolesForm.setData('roles', rolesForm.data.roles.includes(id) ? rolesForm.data.roles.filter((x) => x !== id) : [...rolesForm.data.roles, id]);
    };

    const saveRoles = () => rolesForm.post(route('tenant.admin.administration.users.assign-roles', user.id), { preserveScroll: true });

    const tabs = [
        { value: 'overview', label: 'Overview' },
        { value: 'access', label: 'Access' },
        { value: 'security', label: 'Security' },
    ];

    return (
        <AdministrationShell
            title={user.name}
            description={user.email}
            actions={(
                <>
                    <Button href={route('tenant.admin.administration.users.edit', user.id)} variant="secondary">Edit</Button>
                    <Button href={route('tenant.admin.administration.users.index')} variant="ghost">Back</Button>
                </>
            )}
        >
            <Head title={user.name} />

            <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                <div className="flex items-center gap-4">
                    <Avatar name={user.name} size="lg" />
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-lg font-bold text-slate-900">{user.name}</h2>
                            <Badge tone={statusTone[user.status] || 'neutral'}>{user.status}</Badge>
                        </div>
                        <p className="text-sm text-slate-500">{user.job_title || 'No job title set'} {user.department && `· ${user.department.name}`}</p>
                    </div>
                </div>
            </div>

            <div className="mt-6"><Tabs items={tabs} active={tab} onChange={setTab} /></div>

            <div className="mt-6 space-y-6">
                {tab === 'overview' && (
                    <SectionCard title="Overview">
                        <DescriptionList
                            columns={3}
                            items={[
                                { label: 'Email', value: user.email },
                                { label: 'Phone', value: user.phone },
                                { label: 'Locale', value: user.locale },
                                { label: 'Timezone', value: user.timezone },
                                { label: 'Department', value: user.department?.name },
                                { label: 'Teams', value: user.teams?.map((t) => t.name).join(', ') || null },
                                { label: 'Created', value: user.created_at ? new Date(user.created_at).toLocaleDateString() : null },
                                { label: 'Created by', value: user.created_by?.name },
                            ]}
                        />
                    </SectionCard>
                )}

                {tab === 'access' && (
                    <SectionCard id="access" title="Roles" description="Effective permissions are derived from the roles selected below.">
                        <div className="grid gap-2 sm:grid-cols-2">
                            {allRoles.map((r) => (
                                <label key={r.id} className="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm">
                                    <input type="checkbox" disabled={!canManageRoles} checked={rolesForm.data.roles.includes(r.id)} onChange={() => toggleRole(r.id)} className="rounded border-slate-300 text-navy-800 focus:ring-navy-800 disabled:opacity-50" />
                                    {r.label || r.name}
                                </label>
                            ))}
                        </div>
                        {canManageRoles && (
                            <Button variant="brand" size="sm" className="mt-4" loading={rolesForm.processing} onClick={saveRoles}>Save roles</Button>
                        )}
                        <div className="mt-6 border-t border-slate-100 pt-4">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Effective permissions ({permissions.length})</p>
                            <div className="flex flex-wrap gap-1.5">
                                {permissions.map((p) => <Badge key={p} tone="neutral">{p}</Badge>)}
                            </div>
                        </div>
                    </SectionCard>
                )}

                {tab === 'security' && (
                    <SectionCard title="Security">
                        <DescriptionList
                            columns={2}
                            items={[
                                { label: 'Password last changed', value: user.password_changed_at ? new Date(user.password_changed_at).toLocaleString() : 'Never' },
                                { label: 'Email verified', value: user.email_verified_at ? new Date(user.email_verified_at).toLocaleString() : 'Not verified' },
                                { label: 'Last login', value: user.last_login_at ? new Date(user.last_login_at).toLocaleString() : 'Never' },
                                { label: 'Suspended', value: user.suspended_at ? `${new Date(user.suspended_at).toLocaleString()} by ${user.suspended_by?.name || 'unknown'}` : null },
                            ]}
                        />
                    </SectionCard>
                )}
            </div>

            <section className="mt-8 rounded-lg border border-rose-200 bg-rose-50/40 shadow-soft">
                <div className="border-b border-rose-100 px-5 py-4">
                    <h2 className="text-sm font-semibold text-rose-800">Danger zone</h2>
                    <p className="mt-0.5 text-xs text-rose-700/80">These actions immediately affect this user's access.</p>
                </div>
                <div className="flex flex-wrap gap-2 p-5">
                    {user.status !== 'active' && <Button variant="secondary" onClick={() => router.post(route('tenant.admin.administration.users.activate', user.id))}>Reactivate</Button>}
                    {canSuspend && user.status === 'active' && <Button variant="danger" onClick={() => setDangerAction('suspend')}>Suspend user</Button>}
                    {canDelete && <Button variant="danger" onClick={() => setDangerAction('delete')}>Delete user</Button>}
                </div>
            </section>

            <DangerConfirmDialog
                open={dangerAction === 'suspend'}
                title={`Suspend ${user.name}`}
                consequence="This user will immediately lose access to the workspace."
                reversible
                confirmLabel="Suspend user"
                onCancel={() => setDangerAction(null)}
                onConfirm={() => { router.post(route('tenant.admin.administration.users.suspend', user.id)); setDangerAction(null); }}
            />

            <DangerConfirmDialog
                open={dangerAction === 'delete'}
                title={`Delete ${user.name}`}
                consequence="This permanently removes the user's account and access from this workspace."
                affected={user.email}
                confirmation={user.email}
                reversible={false}
                confirmLabel="Delete user"
                onCancel={() => setDangerAction(null)}
                onConfirm={() => { router.delete(route('tenant.admin.administration.users.destroy', user.id)); setDangerAction(null); }}
            />
        </AdministrationShell>
    );
}
