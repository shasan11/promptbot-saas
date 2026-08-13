import PageHeader from '@/Components/Superadmin/PageHeader';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Switch from '@/Components/UI/Switch';
import Textarea from '@/Components/UI/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';

const money = (value, currency) => `${currency || 'USD'} ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

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
        is_public: plan?.is_public ?? true,
        sort_order: plan?.sort_order || 0,
        is_recommended: plan?.is_recommended ?? false,
        user_limit: plan?.user_limit || '',
        storage_limit_mb: plan?.storage_limit_mb || '',
        unlimited_users: !plan?.user_limit,
        unlimited_storage: !plan?.storage_limit_mb,
        features: featureConfig,
    });

    const updateFeature = (id, key, value) => {
        setData('features', data.features.map((feature) => feature.id === id ? { ...feature, [key]: value } : feature));
    };

    const enabledCount = data.features.filter((feature) => feature.enabled).length;

    const submit = (event) => {
        event.preventDefault();
        transform((payload) => ({
            ...payload,
            user_limit: payload.unlimited_users ? null : payload.user_limit,
            storage_limit_mb: payload.unlimited_storage ? null : payload.storage_limit_mb,
            features: payload.features.filter((feature) => feature.enabled || feature.limit !== '' || feature.unlimited),
        }));
        plan ? patch(route('superadmin.plans.update', plan.public_uuid || plan.id)) : post(route('superadmin.plans.store'));
    };

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={plan ? 'Edit plan' : 'Create plan'}
                    subtitle="Shape pricing, limits, visibility, and recommendation status."
                    actions={<Button href={route('superadmin.plans.index')} variant="secondary">Back to plans</Button>}
                />
            )}
        >
            <Head title={plan ? 'Edit plan' : 'Create plan'} />

            <div className="grid gap-6 xl:grid-cols-[1fr_300px]">
                <form onSubmit={submit} className="space-y-6">
                    <SectionCard title="Plan identity" description="Public naming and ordering for the billing catalog.">
                        <div className="grid gap-5 md:grid-cols-2">
                            <FormField id="name" label="Name" required error={errors.name}>
                                <Input id="name" value={data.name} error={!!errors.name} onChange={(event) => setData('name', event.target.value)} />
                            </FormField>
                            <FormField id="slug" label="Slug" required error={errors.slug}>
                                <Input id="slug" value={data.slug} error={!!errors.slug} onChange={(event) => setData('slug', event.target.value)} />
                            </FormField>
                            <FormField id="description" label="Description" optional error={errors.description} className="md:col-span-2">
                                <Textarea id="description" value={data.description} error={!!errors.description} onChange={(event) => setData('description', event.target.value)} />
                            </FormField>
                        </div>
                    </SectionCard>

                    <SectionCard title="Pricing" description="Keep money fields explicit and easy to scan.">
                        <div className="grid gap-5 md:grid-cols-2">
                            <FormField id="monthly_price" label="Monthly price" required error={errors.monthly_price}>
                                <Input id="monthly_price" type="number" min="0" step="0.01" value={data.monthly_price} error={!!errors.monthly_price} onChange={(event) => setData('monthly_price', event.target.value)} />
                            </FormField>
                            <FormField id="annual_price" label="Annual price" required error={errors.annual_price}>
                                <Input id="annual_price" type="number" min="0" step="0.01" value={data.annual_price} error={!!errors.annual_price} onChange={(event) => setData('annual_price', event.target.value)} />
                            </FormField>
                            <FormField id="currency" label="Currency" required error={errors.currency}>
                                <Input id="currency" value={data.currency} error={!!errors.currency} onChange={(event) => setData('currency', event.target.value.toUpperCase())} />
                            </FormField>
                            <FormField id="trial_days" label="Trial days" optional error={errors.trial_days} hint="0 disables a free trial.">
                                <Input id="trial_days" type="number" min="0" value={data.trial_days} error={!!errors.trial_days} onChange={(event) => setData('trial_days', event.target.value)} />
                            </FormField>
                        </div>
                    </SectionCard>

                    <SectionCard title="Limits and visibility" description="Toggle Unlimited to disable the paired limit field.">
                        <div className="grid gap-5 md:grid-cols-2">
                            <div>
                                <FormField id="user_limit" label="User limit" error={errors.user_limit}>
                                    <Input id="user_limit" type="number" min="0" disabled={data.unlimited_users} value={data.unlimited_users ? '' : data.user_limit} error={!!errors.user_limit} onChange={(event) => setData('user_limit', event.target.value)} />
                                </FormField>
                                <label className="mt-2 flex items-center gap-2 text-xs font-medium text-slate-500">
                                    <input type="checkbox" checked={data.unlimited_users} onChange={(event) => setData('unlimited_users', event.target.checked)} className="rounded border-slate-300 text-navy-800 focus:ring-navy-800" />
                                    Unlimited users
                                </label>
                            </div>
                            <div>
                                <FormField id="storage_limit_mb" label="Storage limit (MB)" error={errors.storage_limit_mb}>
                                    <Input id="storage_limit_mb" type="number" min="0" disabled={data.unlimited_storage} value={data.unlimited_storage ? '' : data.storage_limit_mb} error={!!errors.storage_limit_mb} onChange={(event) => setData('storage_limit_mb', event.target.value)} />
                                </FormField>
                                <label className="mt-2 flex items-center gap-2 text-xs font-medium text-slate-500">
                                    <input type="checkbox" checked={data.unlimited_storage} onChange={(event) => setData('unlimited_storage', event.target.checked)} className="rounded border-slate-300 text-navy-800 focus:ring-navy-800" />
                                    Unlimited storage
                                </label>
                            </div>
                            <FormField id="sort_order" label="Sort order" error={errors.sort_order}>
                                <Input id="sort_order" type="number" value={data.sort_order} error={!!errors.sort_order} onChange={(event) => setData('sort_order', event.target.value)} />
                            </FormField>
                            <div className="space-y-3">
                                <Switch label="Active plan" description="Available for subscription operations" checked={data.is_active} onChange={(value) => setData('is_active', value)} />
                                <Switch label="Published publicly" description="Visible on pricing and customer workspace purchase" checked={data.is_public} onChange={(value) => setData('is_public', value)} />
                                <Switch label="Recommended" description="Highlighted in the catalog" checked={data.is_recommended} onChange={(value) => setData('is_recommended', value)} />
                            </div>
                        </div>
                    </SectionCard>

                    <SectionCard title="Feature matrix" description="Enable boolean features or set limits for metered capabilities.">
                        {features.length ? (
                            <div className="space-y-3">
                                {features.map((feature) => {
                                    const current = data.features.find((item) => item.id === feature.id) || {};

                                    return (
                                        <div key={feature.id} className="grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_auto_auto_160px] md:items-center">
                                            <div>
                                                <div className="font-semibold text-slate-900">{feature.name}</div>
                                                <div className="mt-1 font-mono text-xs text-slate-500">{feature.code}</div>
                                            </div>
                                            <label className="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                                <input type="checkbox" checked={Boolean(current.enabled)} onChange={(event) => updateFeature(feature.id, 'enabled', event.target.checked)} className="rounded border-slate-300 text-navy-800 focus:ring-navy-800" />
                                                Enabled
                                            </label>
                                            <label className="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                                <input type="checkbox" checked={Boolean(current.unlimited)} onChange={(event) => updateFeature(feature.id, 'unlimited', event.target.checked)} className="rounded border-slate-300 text-navy-800 focus:ring-navy-800" />
                                                Unlimited
                                            </label>
                                            <Input type="number" min="0" placeholder="Limit" value={current.limit ?? ''} disabled={Boolean(current.unlimited)} onChange={(event) => updateFeature(feature.id, 'limit', event.target.value)} />
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                                Create features first, then attach them to plans.
                            </p>
                        )}
                    </SectionCard>

                    <div className="sticky bottom-0 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6">
                        <div className="flex justify-end gap-3">
                            <Button href={route('superadmin.plans.index')} variant="secondary">Cancel</Button>
                            <Button type="submit" variant="brand" loading={processing}>Save plan</Button>
                        </div>
                    </div>
                </form>

                <aside className="hidden xl:block">
                    <div className="sticky top-20">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Live preview</p>
                        <div className={`relative rounded-lg border bg-white p-6 shadow-soft ${data.is_recommended ? 'border-brand-300 ring-1 ring-brand-200' : 'border-slate-200'}`}>
                            {data.is_recommended && (
                                <span className="absolute -top-3 left-6 inline-flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-xs font-semibold text-white">
                                    <Sparkles className="h-3 w-3" /> Recommended
                                </span>
                            )}
                            <div className="flex items-start justify-between">
                                <span className="text-base font-bold text-slate-900">{data.name || 'Untitled plan'}</span>
                                <div className="flex gap-1"><Badge tone={data.is_active ? 'brand' : 'neutral'}>{data.is_active ? 'Active' : 'Inactive'}</Badge><Badge tone={data.is_public ? 'success' : 'neutral'}>{data.is_public ? 'Public' : 'Private'}</Badge></div>
                            </div>
                            <div className="mt-4">
                                <span className="text-2xl font-bold text-slate-900">{money(data.monthly_price, data.currency)}</span>
                                <span className="text-sm text-slate-400">/mo</span>
                            </div>
                            <ul className="mt-4 space-y-1.5 text-sm text-slate-600">
                                <li>{data.unlimited_users ? 'Unlimited users' : `${data.user_limit || 0} users`}</li>
                                <li>{data.unlimited_storage ? 'Unlimited storage' : `${data.storage_limit_mb || 0} MB storage`}</li>
                                <li>{enabledCount} feature{enabledCount === 1 ? '' : 's'} enabled</li>
                                {Number(data.trial_days) > 0 && <li>{data.trial_days}-day trial</li>}
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
