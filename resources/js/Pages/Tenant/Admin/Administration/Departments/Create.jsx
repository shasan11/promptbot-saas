import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ department, users, departments }) {
    const editing = Boolean(department);
    const { data, setData, post, put, processing, errors } = useForm({
        name: department?.name || '', code: department?.code || '', description: department?.description || '',
        head_user_id: department?.head_user_id || '', parent_id: department?.parent_id || '', status: department?.status || 'active',
    });

    const submit = (event) => {
        event.preventDefault();
        editing ? put(route('tenant.admin.administration.departments.update', department.id)) : post(route('tenant.admin.administration.departments.store'));
    };

    return (
        <AdministrationShell title={editing ? `Edit ${department.name}` : 'Create department'} actions={<Button href={route('tenant.admin.administration.departments.index')} variant="secondary">Back</Button>}>
            <Head title={editing ? 'Edit department' : 'Create department'} />

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <SectionCard title="Department details">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="name" label="Name" required error={errors.name}>
                            <Input id="name" value={data.name} error={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                        </FormField>
                        <FormField id="code" label="Code" optional error={errors.code}>
                            <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                        </FormField>
                        <FormField id="description" label="Description" optional error={errors.description} className="md:col-span-2">
                            <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        </FormField>
                        <FormField id="head_user_id" label="Department head" optional error={errors.head_user_id}>
                            <Select id="head_user_id" value={data.head_user_id} onChange={(e) => setData('head_user_id', e.target.value)}>
                                <option value="">Unassigned</option>
                                {users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="parent_id" label="Parent department" optional error={errors.parent_id}>
                            <Select id="parent_id" value={data.parent_id} onChange={(e) => setData('parent_id', e.target.value)}>
                                <option value="">None</option>
                                {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="status" label="Status" error={errors.status}>
                            <Select id="status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </Select>
                        </FormField>
                    </div>
                </SectionCard>

                <div className="flex justify-end gap-3">
                    <Button href={route('tenant.admin.administration.departments.index')} variant="secondary">Cancel</Button>
                    <Button type="submit" variant="brand" loading={processing}>{editing ? 'Save changes' : 'Create department'}</Button>
                </div>
            </form>
        </AdministrationShell>
    );
}
