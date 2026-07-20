import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function Detail({ label, value, children }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</div>
            <div className="mt-2 text-sm font-semibold text-slate-950">{children || value || '-'}</div>
        </div>
    );
}

export default function Show({ plan }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={plan.name}
                    subtitle={plan.description || 'Plan details and commercial limits.'}
                    actions={<Link href={route('superadmin.plans.edit', plan.public_uuid || plan.id)} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Edit plan</Link>}
                />
            }
        >
            <Head title={plan.name} />
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Detail label="Monthly">{plan.currency} {plan.monthly_price}</Detail>
                <Detail label="Annual">{plan.currency} {plan.annual_price}</Detail>
                <Detail label="Trial days" value={plan.trial_days} />
                <Detail label="Status"><StatusBadge status={plan.is_active ? 'active' : 'inactive'} /></Detail>
                <Detail label="User limit" value={plan.user_limit ?? 'Unlimited'} />
                <Detail label="Storage MB" value={plan.storage_limit_mb ?? 'Unlimited'} />
                <Detail label="Recommended" value={plan.is_recommended ? 'Yes' : 'No'} />
                <Detail label="Sort order" value={plan.sort_order} />
            </div>

            <section className="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="text-base font-bold text-slate-950">Included Features</h2>
                {plan.features?.length ? (
                    <div className="mt-5 grid gap-3 md:grid-cols-2">
                        {plan.features.map((feature) => (
                            <div key={feature.id} className="rounded-md border border-slate-200 bg-slate-50 p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <div className="font-semibold text-slate-950">{feature.name}</div>
                                        <div className="mt-1 font-mono text-xs text-slate-500">{feature.code}</div>
                                    </div>
                                    <StatusBadge status={feature.pivot?.enabled ? 'active' : 'inactive'} />
                                </div>
                                <div className="mt-3 text-sm text-slate-600">
                                    {feature.pivot?.unlimited ? 'Unlimited' : `Limit: ${feature.pivot?.limit ?? 'Not set'}`}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="mt-5 rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                        No features attached to this plan yet.
                    </div>
                )}
            </section>
        </AuthenticatedLayout>
    );
}
