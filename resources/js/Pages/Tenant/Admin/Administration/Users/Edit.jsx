import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ user, departments }) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: user.name, email: user.email, phone: user.phone || '', job_title: user.job_title || '',
        department_id: user.department_id || '', timezone: user.timezone || '',
    });

    const submit = (event) => {
        event.preventDefault();
        put(route('tenant.admin.administration.users.update', user.id));
    };

    return (
        <AdministrationShell title={`Edit ${user.name}`} actions={<Button href={route('tenant.admin.administration.users.show', user.id)} variant="secondary">Back</Button>}>
            <Head title={`Edit ${user.name}`} />

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <SectionCard title="Identity">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="name" label="Name" required error={errors.name}>
                            <Input id="name" value={data.name} error={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                        </FormField>
                        <FormField id="email" label="Email" required error={errors.email}>
                            <Input id="email" type="email" value={data.email} error={!!errors.email} onChange={(e) => setData('email', e.target.value)} />
                        </FormField>
                        <FormField id="job_title" label="Job title" optional error={errors.job_title}>
                            <Input id="job_title" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                        </FormField>
                        <FormField id="phone" label="Phone" optional error={errors.phone}>
                            <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        </FormField>
                        <FormField id="department_id" label="Department" optional error={errors.department_id}>
                            <Select id="department_id" value={data.department_id} onChange={(e) => setData('department_id', e.target.value)}>
                                <option value="">No department</option>
                                {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </Select>
                        </FormField>
                    </div>
                </SectionCard>

                <div className="flex items-center justify-end gap-3">
                    {isDirty && <span className="mr-auto text-xs font-medium text-amber-700">Unsaved changes</span>}
                    <Button href={route('tenant.admin.administration.users.show', user.id)} variant="secondary">Cancel</Button>
                    <Button type="submit" variant="brand" loading={processing}>Save changes</Button>
                </div>
            </form>
        </AdministrationShell>
    );
}
