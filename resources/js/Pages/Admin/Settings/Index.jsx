import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950';

function GroupForm({ title, subtitle, group, fields }) {
    const initial = Object.fromEntries(fields.map((field) => [field.key, field.value ?? '']));
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm(initial);

    const submit = (event) => {
        event.preventDefault();
        put(route('superadmin.system.settings.update', group));
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h2 className="text-base font-bold text-slate-950">{title}</h2>
                    {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
                </div>
                {recentlySuccessful && <span className="text-xs font-semibold text-emerald-600">Saved</span>}
            </div>
            <div className="grid gap-5 md:grid-cols-2">
                {fields.map((field) => (
                    <label key={field.key} className="block">
                        <span className="text-sm font-semibold text-slate-700">{field.label}</span>
                        <input
                            className={`${inputClass} mt-2`}
                            value={data[field.key]}
                            onChange={(event) => setData(field.key, event.target.value)}
                        />
                        {errors[field.key] && <p className="mt-1 text-xs font-semibold text-rose-600">{errors[field.key]}</p>}
                    </label>
                ))}
            </div>
            <div className="mt-6 flex justify-end">
                <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    {processing ? 'Saving...' : 'Save'}
                </button>
            </div>
        </form>
    );
}

export default function Index({ groups }) {
    return (
        <AuthenticatedLayout header={<PageHeader title="Platform Settings" subtitle="Core platform identity and security thresholds." />}>
            <Head title="Settings" />
            <div className="mx-auto max-w-4xl space-y-6">
                <GroupForm title="General" subtitle="Shown across the platform and in outgoing notifications." group="general" fields={groups.general} />
                <GroupForm title="Security" subtitle="Login attempt limits, lockouts, and password expiry. Applies immediately." group="security" fields={groups.security} />
            </div>
        </AuthenticatedLayout>
    );
}
