import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import DescriptionList from '@/Components/UI/DescriptionList';
import EmptyState from '@/Components/UI/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { CheckCircle2, Inbox, Sparkles, XCircle } from 'lucide-react';

const money = (value, currency) => `${currency} ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function Show({ plan }) {
    const monthlyEquivalent = plan.annual_price ? plan.annual_price / 12 : 0;
    const savingsPct = plan.monthly_price > 0 ? Math.round((1 - monthlyEquivalent / plan.monthly_price) * 100) : 0;

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={plan.name}
                    subtitle={plan.description || 'Plan details and commercial limits.'}
                    actions={<Button href={route('superadmin.plans.edit', plan.public_uuid || plan.id)} variant="brand">Edit plan</Button>}
                />
            )}
        >
            <Head title={plan.name} />

            <div className="grid gap-6 xl:grid-cols-[320px_1fr]">
                <div className={`relative h-fit rounded-lg border bg-white p-6 shadow-soft ${plan.is_recommended ? 'border-brand-300 ring-1 ring-brand-200' : 'border-slate-200'}`}>
                    {plan.is_recommended && (
                        <span className="absolute -top-3 left-6 inline-flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-xs font-semibold text-white">
                            <Sparkles className="h-3 w-3" /> Recommended
                        </span>
                    )}
                    <div className="flex items-start justify-between">
                        <span className="text-lg font-bold text-slate-900">{plan.name}</span>
                        <StatusBadge status={plan.is_active ? 'active' : 'inactive'} />
                    </div>
                    <div className="mt-5">
                        <span className="text-3xl font-bold text-slate-900">{money(plan.monthly_price, plan.currency)}</span>
                        <span className="text-sm text-slate-400">/mo</span>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                        {money(plan.annual_price, plan.currency)} billed annually
                        {savingsPct > 0 && <span className="ml-1 font-semibold text-brand-700">({savingsPct}% cheaper than monthly)</span>}
                    </p>
                    {plan.trial_days > 0 && <p className="mt-3 text-xs text-slate-500">{plan.trial_days}-day free trial included</p>}
                </div>

                <div className="space-y-6">
                    <SectionCard title="Limits">
                        <DescriptionList
                            columns={3}
                            items={[
                                { label: 'User limit', value: plan.user_limit ?? 'Unlimited' },
                                { label: 'Storage', value: plan.storage_limit_mb ? `${plan.storage_limit_mb} MB` : 'Unlimited' },
                                { label: 'Sort order', value: plan.sort_order },
                            ]}
                        />
                    </SectionCard>

                    <SectionCard title="Feature matrix" description="Included, limited, and unlimited capabilities for this plan.">
                        {plan.features?.length ? (
                            <div className="grid gap-3 md:grid-cols-2">
                                {plan.features.map((feature) => {
                                    const enabled = Boolean(feature.pivot?.enabled);
                                    return (
                                        <div key={feature.id} className="flex items-start justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 p-4">
                                            <div>
                                                <div className="font-semibold text-slate-900">{feature.name}</div>
                                                <div className="mt-1 font-mono text-xs text-slate-500">{feature.code}</div>
                                                <div className="mt-2 text-xs text-slate-600">
                                                    {feature.pivot?.unlimited ? <Badge tone="brand">Unlimited</Badge> : feature.pivot?.limit ? <Badge tone="info">Limit: {feature.pivot.limit}</Badge> : <Badge tone="neutral">No limit set</Badge>}
                                                </div>
                                            </div>
                                            {enabled ? <CheckCircle2 className="h-4 w-4 shrink-0 text-brand-600" /> : <XCircle className="h-4 w-4 shrink-0 text-slate-300" />}
                                        </div>
                                    );
                                })}
                            </div>
                        ) : <EmptyState icon={Inbox} title="No features attached" description="Attach features to this plan from the editor." />}
                    </SectionCard>

                    {!plan.is_active && (
                        <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            This plan is archived — it stays visible on existing subscribers' billing history, but new subscriptions can no longer select it.
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
