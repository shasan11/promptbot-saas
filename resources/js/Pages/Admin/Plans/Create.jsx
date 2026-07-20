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

function Toggle({ label, checked, onChange }) {
    return (
        <label className="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="rounded border-slate-300 text-slate-950 focus:ring-slate-950" />
        </label>
    );
}

function Panel({ title, subtitle, children }) {
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

export default function Create({ plan = null, features = [] }) {
    const featureConfig = features.map((feature) => {
        const existing = plan?.features?.find((item) => item.id === feature.id);

        return {
            id: feature.id,
            enabled: existing ? Boolean(existing.pivot?.enabled) : false,
            limit: existing?.pivot?.limit ?? '',
            unlimited: existing ? Boolean(existing.pivot?.unlimited) : false,
        };
    });

    const { data, setData, post, patch, processing, errors, transform } = useForm({
        name: plan?.name || '',
        slug: plan?.slug || '',
        description: plan?.description || '',
        monthly_price: plan?.monthly_price || 0,
        annual_price: plan?.annual_price || 0,
        currency: plan?.currency || 'USD',
        trial_days: plan?.trial_days || 0,
        is_active: plan?.is_active ?? true,
        sort_order: plan?.sort_order || 0,
        is_recommended: plan?.is_recommended ?? false,
        user_limit: plan?.user_limit || '',
        storage_limit_mb: plan?.storage_limit_mb || '',
        features: featureConfig,
    });

    const updateFeature = (id, key, value) => {
        setData('features', data.features.map((feature) => feature.id === id ? { ...feature, [key]: value } : feature));
    };

    const submit = (event) => {
        event.preventDefault();
        transform((payload) => ({
            ...payload,
            features: payload.features.filter((feature) => feature.enabled || feature.limit !== '' || feature.unlimited),
        }));
        plan ? patch(route('superadmin.plans.update', plan.public_uuid || plan.id)) : post(route('superadmin.plans.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={plan ? 'Edit Plan' : 'Create Plan'}
                    subtitle="Shape pricing, limits, visibility, and recommendation status."
                    actions={<Link href={route('superadmin.plans.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to plans</Link>}
                />
            }
        >
            <Head title="Plan" />

            <form onSubmit={submit} className="mx-auto max-w-5xl space-y-6">
                <Panel title="Plan Details" subtitle="Public naming and ordering for the billing catalog.">
                    <Field label="Name" error={errors.name}>
                        <input className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} />
                    </Field>
                    <Field label="Slug" error={errors.slug}>
                        <input className={inputClass} value={data.slug} onChange={(event) => setData('slug', event.target.value)} />
                    </Field>
                    <Field label="Description" error={errors.description} className="md:col-span-2">
                        <textarea className={`${inputClass} min-h-28`} value={data.description} onChange={(event) => setData('description', event.target.value)} />
                    </Field>
                </Panel>

                <Panel title="Pricing" subtitle="Keep money fields explicit and easy to scan.">
                    <Field label="Monthly price" error={errors.monthly_price}>
                        <input className={inputClass} type="number" min="0" step="0.01" value={data.monthly_price} onChange={(event) => setData('monthly_price', event.target.value)} />
                    </Field>
                    <Field label="Annual price" error={errors.annual_price}>
                        <input className={inputClass} type="number" min="0" step="0.01" value={data.annual_price} onChange={(event) => setData('annual_price', event.target.value)} />
                    </Field>
                    <Field label="Currency" error={errors.currency}>
                        <input className={inputClass} value={data.currency} onChange={(event) => setData('currency', event.target.value.toUpperCase())} />
                    </Field>
                    <Field label="Trial days" error={errors.trial_days}>
                        <input className={inputClass} type="number" min="0" value={data.trial_days} onChange={(event) => setData('trial_days', event.target.value)} />
                    </Field>
                </Panel>

                <Panel title="Limits and Display" subtitle="Use blank limits when the plan should behave as unlimited.">
                    <Field label="User limit" error={errors.user_limit}>
                        <input className={inputClass} type="number" min="0" value={data.user_limit} onChange={(event) => setData('user_limit', event.target.value)} />
                    </Field>
                    <Field label="Storage limit MB" error={errors.storage_limit_mb}>
                        <input className={inputClass} type="number" min="0" value={data.storage_limit_mb} onChange={(event) => setData('storage_limit_mb', event.target.value)} />
                    </Field>
                    <Field label="Sort order" error={errors.sort_order}>
                        <input className={inputClass} type="number" value={data.sort_order} onChange={(event) => setData('sort_order', event.target.value)} />
                    </Field>
                    <div className="space-y-3">
                        <Toggle label="Active plan" checked={data.is_active} onChange={(value) => setData('is_active', value)} />
                        <Toggle label="Recommended" checked={data.is_recommended} onChange={(value) => setData('is_recommended', value)} />
                    </div>
                </Panel>

                <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-6">
                        <h2 className="text-base font-bold text-slate-950">Plan Features</h2>
                        <p className="mt-1 text-sm text-slate-500">Enable boolean features or set limits for limited capabilities.</p>
                    </div>
                    {features.length ? (
                        <div className="grid gap-4">
                            {features.map((feature) => {
                                const current = data.features.find((item) => item.id === feature.id) || {};

                                return (
                                    <div key={feature.id} className="grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_auto_auto_160px] md:items-center">
                                        <div>
                                            <div className="font-semibold text-slate-950">{feature.name}</div>
                                            <div className="mt-1 font-mono text-xs text-slate-500">{feature.code}</div>
                                        </div>
                                        <label className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                            <input
                                                type="checkbox"
                                                checked={Boolean(current.enabled)}
                                                onChange={(event) => updateFeature(feature.id, 'enabled', event.target.checked)}
                                                className="rounded border-slate-300 text-slate-950 focus:ring-slate-950"
                                            />
                                            Enabled
                                        </label>
                                        <label className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                            <input
                                                type="checkbox"
                                                checked={Boolean(current.unlimited)}
                                                onChange={(event) => updateFeature(feature.id, 'unlimited', event.target.checked)}
                                                className="rounded border-slate-300 text-slate-950 focus:ring-slate-950"
                                            />
                                            Unlimited
                                        </label>
                                        <input
                                            className={inputClass}
                                            type="number"
                                            min="0"
                                            placeholder="Limit"
                                            value={current.limit ?? ''}
                                            disabled={Boolean(current.unlimited)}
                                            onChange={(event) => updateFeature(feature.id, 'limit', event.target.value)}
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                            Create features first, then attach them to plans.
                        </div>
                    )}
                </section>

                <div className="flex justify-end gap-3">
                    <Link href={route('superadmin.plans.index')} className="rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Cancel
                    </Link>
                    <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {processing ? 'Saving...' : 'Save plan'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
