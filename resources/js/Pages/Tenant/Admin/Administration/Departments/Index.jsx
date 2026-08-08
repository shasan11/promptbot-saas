import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import EmptyState from '@/Components/UI/EmptyState';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, router, usePage } from '@inertiajs/react';
import { Building2, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Index({ departments }) {
    const { auth } = usePage().props;
    const canCreate = auth?.permissions?.includes('departments.create');
    const [deleting, setDeleting] = useState(null);

    return (
        <AdministrationShell
            title="Departments"
            description="Organizational divisions used to group users and teams."
            actions={canCreate && <Button href={route('tenant.admin.administration.departments.create')} variant="brand" icon={Plus}>Create department</Button>}
        >
            <Head title="Departments" />

            {departments.length ? (
                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                    <div className="divide-y divide-slate-100">
                        {departments.map((department) => (
                            <div key={department.id} className="flex items-center justify-between px-5 py-4">
                                <div>
                                    <div className="flex items-center gap-2 font-medium text-slate-900">{department.name} <Badge tone={department.status === 'active' ? 'brand' : 'neutral'}>{department.status}</Badge></div>
                                    <p className="mt-0.5 text-xs text-slate-500">{department.users_count} user{department.users_count === 1 ? '' : 's'} · Head: {department.head?.name || 'Unassigned'}</p>
                                </div>
                                <DropdownMenu
                                    items={[
                                        { label: 'Edit', icon: Pencil, onClick: () => router.visit(route('tenant.admin.administration.departments.edit', department.id)) },
                                        { label: 'Delete', icon: Trash2, danger: true, onClick: () => setDeleting(department) },
                                    ]}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            ) : (
                <EmptyState icon={Building2} title="No departments yet" description="Create departments to reflect your organization's structure." action={canCreate && <Button href={route('tenant.admin.administration.departments.create')} variant="brand" icon={Plus}>Create department</Button>} />
            )}

            <ConfirmDialog
                open={!!deleting}
                title={`Delete "${deleting?.name}"?`}
                variant="danger"
                confirmLabel="Delete"
                onCancel={() => setDeleting(null)}
                onConfirm={() => { router.delete(route('tenant.admin.administration.departments.destroy', deleting.id), { preserveScroll: true }); setDeleting(null); }}
            >
                Departments with active users can't be deleted — reassign users first.
            </ConfirmDialog>
        </AdministrationShell>
    );
}
