import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950';

function Field({ label, error, children, className = '' }) {
    return (
        <label className={`block ${className}`}>
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            <div className="mt-2">{children}</div>
            {error && <p className="mt-1 text-xs font-semibold text-rose-600">{error}</p>}
        </label>
    );
}

export default function Create({ feature = null }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: feature?.name || '',
        code: feature?.code || '',
        description: feature?.description || '',
        type: feature?.type || 'boolean',
    });

    const submit = (event) => {
        event.preventDefault();
        feature ? patch(route('superadmin.features.update', feature.public_uuid || feature.id)) : post(route('superadmin.features.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={feature ? 'Edit Feature' : 'Create Feature'}
                    subtitle="Create feature flags and limited capabilities that plans can consume."
                    actions={<Link href={route('superadmin.features.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to features</Link>}
                />
            }
        >
            <Head title="Feature" />

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6">
                <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-6">
                        <h2 className="text-base font-bold text-slate-950">Feature Details</h2>
                        <p className="mt-1 text-sm text-slate-500">Use stable codes because they become the backend contract for limits and flags.</p>
                    </div>
                    <div className="grid gap-5 md:grid-cols-2">
                        <Field label="Name" error={errors.name}>
                            <input className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} />
                        </Field>
                        <Field label="Code" error={errors.code}>
                            <input className={inputClass} value={data.code} onChange={(event) => setData('code', event.target.value)} />
                        </Field>
                        <Field label="Type" error={errors.type} className="md:col-span-2">
                            <select className={inputClass} value={data.type} onChange={(event) => setData('type', event.target.value)}>
                                <option value="boolean">Boolean</option>
                                <option value="limited">Limited</option>
                            </select>
                        </Field>
                        <Field label="Description" error={errors.description} className="md:col-span-2">
                            <textarea className={`${inputClass} min-h-32`} value={data.description} onChange={(event) => setData('description', event.target.value)} />
                        </Field>
                    </div>
                </section>

                <div className="flex justify-end gap-3">
                    <Link href={route('superadmin.features.index')} className="rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Cancel
                    </Link>
                    <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {processing ? 'Saving...' : 'Save feature'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
