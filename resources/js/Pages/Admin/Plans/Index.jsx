import PageHeader from '@/Components/Superadmin/PageHeader';
import Pagination from '@/Components/Superadmin/Pagination';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import DangerConfirmDialog from '@/Components/UI/DangerConfirmDialog';
import EmptyState from '@/Components/UI/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Sparkles, Tags } from 'lucide-react';
import { useState } from 'react';

const money = (value, currency) => `${currency} ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function Index({ plans }) {
    const rows = plans?.data || [];
    const [archiving, setArchiving] = useState(null);

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="Plans"
                    subtitle="Manage pricing packages, catalog order, and plan visibility."
                    actions={<Button href={route('superadmin.plans.create')} variant="brand" icon={Plus}>Create plan</Button>}
                />
            )}
        >
            <Head title="Plans" />

            {rows.length ? (
                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {rows.map((plan) => (
                        <div key={plan.id} className={`relative flex flex-col rounded-lg border bg-white p-6 shadow-soft ${plan.is_recommended ? 'border-brand-300 ring-1 ring-brand-200' : 'border-slate-200'}`}>
                            {plan.is_recommended && (
                                <span className="absolute -top-3 left-6 inline-flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-xs font-semibold text-white">
                                    <Sparkles className="h-3 w-3" /> Recommended
                                </span>
                            )}
                            <div className="flex items-start justify-between">
                                <div>
                                    <Link href={route('superadmin.plans.show', plan.public_uuid || plan.id)} className="text-base font-bold text-slate-900 hover:text-brand-700">{plan.name}</Link>
                                    <p className="mt-0.5 font-mono text-xs text-slate-400">{plan.slug}</p>
                                </div>
                                <div className="flex flex-wrap gap-1"><Badge tone={plan.is_active ? 'brand' : 'neutral'}>{plan.is_active ? 'Active' : 'Inactive'}</Badge><Badge tone={plan.is_public ? 'success' : 'neutral'}>{plan.is_public ? 'Public' : 'Private'}</Badge></div>
                            </div>

                            <div className="mt-5">
                                <div className="flex items-baseline gap-1">
                                    <span className="text-3xl font-bold tracking-tight text-slate-900">{money(plan.monthly_price, plan.currency)}</span>
                                    <span className="text-sm text-slate-400">/mo</span>
                                </div>
                                <p className="mt-1 text-xs text-slate-500">{money(plan.annual_price, plan.currency)} billed annually</p>
                            </div>

                            <ul className="mt-5 space-y-1.5 text-sm text-slate-600">
                                <li>{plan.user_limit ? `${plan.user_limit} users` : 'Unlimited users'}</li>
                                <li>{plan.storage_limit_mb ? `${plan.storage_limit_mb} MB storage` : 'Unlimited storage'}</li>
                                <li>{plan.features?.length ?? 0} feature{(plan.features?.length ?? 0) === 1 ? '' : 's'} included</li>
                                {plan.trial_days > 0 && <li>{plan.trial_days}-day trial</li>}
                            </ul>

                            <div className="mt-6 flex gap-2 border-t border-slate-100 pt-4">
                                <Button href={route('superadmin.plans.edit', plan.public_uuid || plan.id)} variant="secondary" size="sm" className="flex-1">Edit</Button>
                                <Button variant="ghost" size="sm" onClick={() => setArchiving(plan)}>Archive</Button>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <EmptyState icon={Tags} title="No plans yet" description="Create your first commercial plan to start selling subscriptions." action={<Button href={route('superadmin.plans.create')} variant="brand" icon={Plus}>Create plan</Button>} />
            )}

            <Pagination links={plans?.links} />

            <DangerConfirmDialog
                open={!!archiving}
                title={`Archive ${archiving?.name}`}
                consequence="Archived plans stay visible on existing subscribers' billing history, but new subscriptions can no longer select this plan."
                affected={archiving?.slug}
                reversible={false}
                confirmLabel="Archive plan"
                onCancel={() => setArchiving(null)}
                onConfirm={() => {
                    router.delete(route('superadmin.plans.destroy', archiving.public_uuid || archiving.id), {
                        preserveScroll: true,
                        onFinish: () => setArchiving(null),
                    });
                }}
            />
        </AuthenticatedLayout>
    );
}
