import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

function Field({ label, error, hint, className = '', children }) {
    return (
        <label className={`block ${className}`}>
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            <div className="mt-2">{children}</div>
            {hint && !error && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
            {error && <p className="mt-1 text-xs font-semibold text-rose-600">{error}</p>}
        </label>
    );
}

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950';

function Section({ title, subtitle, children }) {
    return (
        <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-6">
                <h2 className="text-base font-bold text-slate-950">{title}</h2>
                {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
            </div>
            <div className="grid gap-5 md:grid-cols-2">{children}</div>
        </section>
    );
}

export default function Create({ plans = [], tenant = null, provisioningMode = 'manual' }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        company_name: tenant?.company_name || '',
        slug: tenant?.slug || '',
        owner_name: '',
        owner_email: '',
        owner_password: '',
        plan_id: tenant?.plan_id || plans[0]?.id || '',
        provisioning_mode: provisioningMode,
        database_host: '127.0.0.1',
        database_port: 3306,
        database_name: '',
        database_username: '',
        database_password: '',
    });

    const submit = (event) => {
        event.preventDefault();
        tenant ? patch(route('superadmin.tenants.update', tenant.public_uuid || tenant.id)) : post(route('superadmin.tenants.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={tenant ? 'Edit Tenant' : 'Create Tenant'}
                    subtitle="Provision the customer workspace, owner account, plan, and database connection."
                    actions={
                        <Link href={route('superadmin.tenants.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Back to tenants
                        </Link>
                    }
                />
            }
        >
            <Head title="Tenant" />

            <form onSubmit={submit} className="mx-auto max-w-5xl space-y-6">
                <Section title="Company" subtitle="This becomes the tenant identity customers recognize.">
                    <Field label="Company name" error={errors.company_name}>
                        <input className={inputClass} value={data.company_name} onChange={(event) => setData('company_name', event.target.value)} />
                    </Field>
                    <Field label="Tenant slug" error={errors.slug} hint="Used for tenant routing and generated hostnames.">
                        <input className={inputClass} value={data.slug} onChange={(event) => setData('slug', event.target.value)} />
                    </Field>
                </Section>

                <Section title="Owner Account" subtitle="The first tenant admin who signs in from the tenant domain.">
                    <Field label="Owner name" error={errors.owner_name}>
                        <input className={inputClass} value={data.owner_name} onChange={(event) => setData('owner_name', event.target.value)} />
                    </Field>
                    <Field label="Owner email" error={errors.owner_email}>
                        <input className={inputClass} type="email" value={data.owner_email} onChange={(event) => setData('owner_email', event.target.value)} />
                    </Field>
                    <Field label="Owner password" error={errors.owner_password} className="md:col-span-2">
                        <input className={inputClass} type="password" value={data.owner_password} onChange={(event) => setData('owner_password', event.target.value)} autoComplete="new-password" />
                    </Field>
                </Section>

                <Section title="Plan" subtitle="Attach the tenant to an active commercial package.">
                    <Field label="Plan" error={errors.plan_id} className="md:col-span-2">
                        <select className={inputClass} value={data.plan_id} onChange={(event) => setData('plan_id', event.target.value)}>
                            <option value="">Select a plan</option>
                            {plans.map((plan) => (
                                <option key={plan.id} value={plan.id}>{plan.name}</option>
                            ))}
                        </select>
                    </Field>
                </Section>

                <Section title="Database" subtitle="Password can be blank when your local database user allows it.">
                    <Field label="Host" error={errors.database_host}>
                        <input className={inputClass} value={data.database_host} onChange={(event) => setData('database_host', event.target.value)} />
                    </Field>
                    <Field label="Port" error={errors.database_port}>
                        <input className={inputClass} type="number" value={data.database_port} onChange={(event) => setData('database_port', event.target.value)} />
                    </Field>
                    <Field label="Database name" error={errors.database_name}>
                        <input className={inputClass} value={data.database_name} onChange={(event) => setData('database_name', event.target.value)} />
                    </Field>
                    <Field label="Database username" error={errors.database_username}>
                        <input className={inputClass} value={data.database_username} onChange={(event) => setData('database_username', event.target.value)} />
                    </Field>
                    <Field label="Database password" error={errors.database_password} hint="Leave empty for passwordless local users." className="md:col-span-2">
                        <input className={inputClass} type="password" value={data.database_password} onChange={(event) => setData('database_password', event.target.value)} autoComplete="new-password" />
                    </Field>
                </Section>

                <div className="flex justify-end gap-3">
                    <Link href={route('superadmin.tenants.index')} className="rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Cancel
                    </Link>
                    <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {processing ? 'Saving...' : 'Save tenant'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
