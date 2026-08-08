import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import EmptyState from '@/Components/UI/EmptyState';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Shield, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Index({ roles }) {
    const { auth } = usePage().props;
    const canCreate = auth?.permissions?.includes('roles.create');
    const [deleting, setDeleting] = useState(null);

    return (
        <AdministrationShell
            title="Roles & permissions"
            description="Define what each role can see and do across the workspace."
            actions={canCreate && <Button href={route('tenant.admin.administration.roles.create')} variant="brand" icon={Plus}>Create role</Button>}
        >
            <Head title="Roles & permissions" />

            {roles.length ? (
                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                    <div className="divide-y divide-slate-100">
                        {roles.map((role) => (
                            <div key={role.id} className="flex items-center justify-between px-5 py-4">
                                <div>
                                    <div className="flex items-center gap-2 font-medium text-slate-900">
                                        {role.label || role.name}
                                        {role.is_protected && <Badge tone="info">System</Badge>}
                                    </div>
                                    <p className="mt-0.5 text-xs text-slate-500">{role.users_count} user{role.users_count === 1 ? '' : 's'} · {role.permissions_count} permission{role.permissions_count === 1 ? '' : 's'}</p>
                                </div>
                                <DropdownMenu
                                    items={[
                                        { label: 'Edit', icon: Pencil, onClick: () => router.visit(route('tenant.admin.administration.roles.edit', role.id)) },
                                        ...(role.is_protected ? [] : [{ label: 'Delete', icon: Trash2, danger: true, onClick: () => setDeleting(role) }]),
                                    ]}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            ) : (
                <EmptyState icon={Shield} title="No roles yet" description="Create roles to control what your team can access." action={canCreate && <Button href={route('tenant.admin.administration.roles.create')} variant="brand" icon={Plus}>Create role</Button>} />
            )}

            <ConfirmDialog
                open={!!deleting}
                title={`Delete "${deleting?.label || deleting?.name}"?`}
                variant="danger"
                confirmLabel="Delete"
                onCancel={() => setDeleting(null)}
                onConfirm={() => { router.delete(route('tenant.admin.administration.roles.destroy', deleting.id), { preserveScroll: true }); setDeleting(null); }}
            >
                Roles with assigned users can't be deleted — reassign those users first.
            </ConfirmDialog>
        </AdministrationShell>
    );
}
