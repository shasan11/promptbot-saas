import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ team, users, departments }) {
    const editing = Boolean(team);
    const { data, setData, post, put, processing, errors } = useForm({
        name: team?.name || '', description: team?.description || '', lead_user_id: team?.lead_user_id || '',
        department_id: team?.department_id || '', color: team?.color || '#059669', status: team?.status || 'active',
    });

    const submit = (event) => {
        event.preventDefault();
        editing ? put(route('tenant.admin.administration.teams.update', team.id)) : post(route('tenant.admin.administration.teams.store'));
    };

    return (
        <AdministrationShell title={editing ? `Edit ${team.name}` : 'Create team'} actions={<Button href={route('tenant.admin.administration.teams.index')} variant="secondary">Back</Button>}>
            <Head title={editing ? 'Edit team' : 'Create team'} />

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <SectionCard title="Team details">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="name" label="Name" required error={errors.name} className="md:col-span-2">
                            <Input id="name" value={data.name} error={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                        </FormField>
                        <FormField id="description" label="Description" optional error={errors.description} className="md:col-span-2">
                            <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        </FormField>
                        <FormField id="lead_user_id" label="Team lead" optional error={errors.lead_user_id}>
                            <Select id="lead_user_id" value={data.lead_user_id} onChange={(e) => setData('lead_user_id', e.target.value)}>
                                <option value="">Unassigned</option>
                                {users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="department_id" label="Department" optional error={errors.department_id}>
                            <Select id="department_id" value={data.department_id} onChange={(e) => setData('department_id', e.target.value)}>
                                <option value="">No department</option>
                                {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </Select>
                        </FormField>
                        <FormField id="color" label="Color" error={errors.color}>
                            <input id="color" type="color" value={data.color} onChange={(e) => setData('color', e.target.value)} className="h-10 w-16 rounded-md border border-slate-300" />
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
                    <Button href={route('tenant.admin.administration.teams.index')} variant="secondary">Cancel</Button>
                    <Button type="submit" variant="brand" loading={processing}>{editing ? 'Save changes' : 'Create team'}</Button>
                </div>
            </form>
        </AdministrationShell>
    );
}
