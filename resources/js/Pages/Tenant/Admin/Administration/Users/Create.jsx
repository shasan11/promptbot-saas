import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ roles, departments, teams }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '', email: '', password: '', phone: '', job_title: '', department_id: '', timezone: '', roles: [], team_ids: [],
    });

    const toggle = (key, id) => {
        setData(key, data[key].includes(id) ? data[key].filter((x) => x !== id) : [...data[key], id]);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('tenant.admin.administration.users.store'));
    };

    return (
        <AdministrationShell title="Add user" description="Create a new account with immediate access to this workspace." actions={<Button href={route('tenant.admin.administration.users.index')} variant="secondary">Back</Button>}>
            <Head title="Add user" />

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <SectionCard title="Identity">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField id="name" label="Name" required error={errors.name}>
                            <Input id="name" value={data.name} error={!!errors.name} onChange={(e) => setData('name', e.target.value)} />
                        </FormField>
                        <FormField id="email" label="Email" required error={errors.email}>
                            <Input id="email" type="email" value={data.email} error={!!errors.email} onChange={(e) => setData('email', e.target.value)} />
                        </FormField>
                        <FormField id="password" label="Temporary password" required error={errors.password} hint="Share this securely — the user should change it after first login.">
                            <Input id="password" type="password" value={data.password} error={!!errors.password} onChange={(e) => setData('password', e.target.value)} />
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

                <SectionCard title="Roles" description="Grant access by assigning one or more roles.">
                    <div className="grid gap-2 sm:grid-cols-2">
                        {roles.map((r) => (
                            <label key={r.id} className="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm">
                                <input type="checkbox" checked={data.roles.includes(r.id)} onChange={() => toggle('roles', r.id)} className="rounded border-slate-300 text-navy-800 focus:ring-navy-800" />
                                {r.label || r.name}
                            </label>
                        ))}
                    </div>
                </SectionCard>

                <SectionCard title="Teams" description="Optionally add this user to one or more teams.">
                    <div className="grid gap-2 sm:grid-cols-2">
                        {teams.map((t) => (
                            <label key={t.id} className="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm">
                                <input type="checkbox" checked={data.team_ids.includes(t.id)} onChange={() => toggle('team_ids', t.id)} className="rounded border-slate-300 text-navy-800 focus:ring-navy-800" />
                                {t.name}
                            </label>
                        ))}
                        {!teams.length && <p className="text-sm text-slate-500">No teams created yet.</p>}
                    </div>
                </SectionCard>

                <div className="flex justify-end gap-3">
                    <Button href={route('tenant.admin.administration.users.index')} variant="secondary">Cancel</Button>
                    <Button type="submit" variant="brand" loading={processing}>Create user</Button>
                </div>
            </form>
        </AdministrationShell>
    );
}
