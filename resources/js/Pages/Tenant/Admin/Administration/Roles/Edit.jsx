import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ role, permissionGroups }) {
    const editing = Boolean(role);
    const { data, setData, post, put, processing, errors, isDirty } = useForm({
        name: role?.name || '',
        label: role?.label || '',
        permissions: role?.permissions?.map((p) => p.id) || [],
    });

    const togglePermission = (id) => {
        setData('permissions', data.permissions.includes(id) ? data.permissions.filter((x) => x !== id) : [...data.permissions, id]);
    };

    const toggleGroup = (groupPermissions, allSelected) => {
        const ids = groupPermissions.map((p) => p.id);
        setData('permissions', allSelected ? data.permissions.filter((id) => !ids.includes(id)) : [...new Set([...data.permissions, ...ids])]);
    };

    const submit = (event) => {
        event.preventDefault();
        editing ? put(route('tenant.admin.administration.roles.update', role.id)) : post(route('tenant.admin.administration.roles.store'));
    };

    return (
        <AdministrationShell title={editing ? `Edit ${role.label}` : 'Create role'} actions={<Button href={route('tenant.admin.administration.roles.index')} variant="secondary">Back</Button>}>
            <Head title={editing ? 'Edit role' : 'Create role'} />

            {editing && role.is_protected && (
                <Alert tone="info" className="mb-6">This is a protected system role. Its identity can't be changed, but you can review its permissions.</Alert>
            )}

            <form onSubmit={submit} className="max-w-4xl space-y-6">
                <SectionCard title="Role identity">
                    <div className="grid gap-5 md:grid-cols-2">
                        {!editing && (
                            <FormField id="name" label="Internal name" required error={errors.name} hint="Used internally; cannot be changed after creation.">
                                <Input id="name" value={data.name} error={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                            </FormField>
                        )}
                        <FormField id="label" label="Display label" required error={errors.label}>
                            <Input id="label" value={data.label} error={!!errors.label} disabled={role?.is_protected} onChange={(e) => setData('label', e.target.value)} />
                        </FormField>
                    </div>
                    {editing && <p className="mt-3 text-xs text-slate-500">{role.users_count} user{role.users_count === 1 ? '' : 's'} currently hold this role.</p>}
                </SectionCard>

                <SectionCard title="Permission matrix" description="Some permissions automatically require others (e.g. editing users requires viewing users) — this is enforced on save regardless of what's checked here.">
                    <div className="space-y-6">
                        {Object.entries(permissionGroups).map(([group, permissions]) => {
                            const allSelected = permissions.every((p) => data.permissions.includes(p.id));
                            return (
                                <div key={group}>
                                    <div className="mb-2 flex items-center justify-between">
                                        <h3 className="text-sm font-semibold text-slate-800">{group}</h3>
                                        <button type="button" onClick={() => toggleGroup(permissions, allSelected)} className="text-xs font-semibold text-navy-800 hover:text-brand-700">
                                            {allSelected ? 'Clear group' : 'Select all'}
                                        </button>
                                    </div>
                                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                        {permissions.map((permission) => (
                                            <label key={permission.id} className="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={data.permissions.includes(permission.id)}
                                                    onChange={() => togglePermission(permission.id)}
                                                    className="rounded border-slate-300 text-navy-800 focus:ring-navy-800"
                                                />
                                                {permission.label || permission.name}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </SectionCard>

                <div className="sticky bottom-0 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6">
                    <div className="flex items-center justify-end gap-3">
                        {isDirty && <span className="mr-auto text-xs font-medium text-amber-700">Unsaved changes · {data.permissions.length} permission(s) selected</span>}
                        <Button href={route('tenant.admin.administration.roles.index')} variant="secondary">Cancel</Button>
                        <Button type="submit" variant="brand" loading={processing}>{editing ? 'Save role' : 'Create role'}</Button>
                    </div>
                </div>
            </form>
        </AdministrationShell>
    );
}
