import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const STATUSES = ['trial', 'active', 'past_due', 'cancelled', 'expired', 'suspended', 'manual'];
const INTERVALS = ['monthly', 'annual'];

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950';

function Field({ label, error, children }) {
    return (
        <label className="block">
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            <div className="mt-2">{children}</div>
            {error && <p className="mt-1 text-xs font-semibold text-rose-600">{error}</p>}
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

const toDateInput = (value) => (value ? String(value).slice(0, 10) : '');

export default function Show({ subscription, plans = [] }) {
    const { data, setData, patch, processing, errors } = useForm({
        plan_id: subscription.plan_id || '',
        status: subscription.status || 'active',
        billing_interval: subscription.billing_interval || 'monthly',
        trial_ends_at: toDateInput(subscription.trial_ends_at),
        current_period_starts_at: toDateInput(subscription.current_period_starts_at),
        current_period_ends_at: toDateInput(subscription.current_period_ends_at),
        grace_ends_at: toDateInput(subscription.grace_ends_at),
        cancelled_at: toDateInput(subscription.cancelled_at),
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('superadmin.subscriptions.update', subscription.public_uuid || subscription.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Subscription"
                    subtitle={`${subscription.tenant?.company_name || 'Tenant'} on ${subscription.plan?.name || 'unknown plan'}`}
                    actions={<Link href={route('superadmin.subscriptions.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back</Link>}
                />
            }
        >
            <Head title="Subscription" />

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6">
                <Panel title="Plan and status" subtitle="Changing the plan here updates the tenant's plan too; changing status suspends or reactivates the tenant.">
                    <Field label="Plan" error={errors.plan_id}>
                        <select className={inputClass} value={data.plan_id} onChange={(event) => setData('plan_id', event.target.value)}>
                            {plans.map((plan) => <option key={plan.id} value={plan.id}>{plan.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            {STATUSES.map((status) => <option key={status} value={status}>{status}</option>)}
                        </select>
                    </Field>
                    <Field label="Billing interval" error={errors.billing_interval}>
                        <select className={inputClass} value={data.billing_interval} onChange={(event) => setData('billing_interval', event.target.value)}>
                            {INTERVALS.map((interval) => <option key={interval} value={interval}>{interval}</option>)}
                        </select>
                    </Field>
                    <Field label="Identifier">
                        <div className="rounded-md bg-slate-50 px-3 py-2.5 font-mono text-sm text-slate-500">{subscription.public_uuid || subscription.id}</div>
                    </Field>
                </Panel>

                <Panel title="Lifecycle dates" subtitle="Leave a date blank to clear it.">
                    <Field label="Trial ends" error={errors.trial_ends_at}>
                        <input type="date" className={inputClass} value={data.trial_ends_at} onChange={(event) => setData('trial_ends_at', event.target.value)} />
                    </Field>
                    <Field label="Current period starts" error={errors.current_period_starts_at}>
                        <input type="date" className={inputClass} value={data.current_period_starts_at} onChange={(event) => setData('current_period_starts_at', event.target.value)} />
                    </Field>
                    <Field label="Current period ends" error={errors.current_period_ends_at}>
                        <input type="date" className={inputClass} value={data.current_period_ends_at} onChange={(event) => setData('current_period_ends_at', event.target.value)} />
                    </Field>
                    <Field label="Grace period ends" error={errors.grace_ends_at}>
                        <input type="date" className={inputClass} value={data.grace_ends_at} onChange={(event) => setData('grace_ends_at', event.target.value)} />
                    </Field>
                    <Field label="Cancelled at" error={errors.cancelled_at}>
                        <input type="date" className={inputClass} value={data.cancelled_at} onChange={(event) => setData('cancelled_at', event.target.value)} />
                    </Field>
                    <Field label="Current status">
                        <div className="mt-1"><StatusBadge status={subscription.status} /></div>
                    </Field>
                </Panel>

                <div className="flex justify-end gap-3">
                    <Link href={route('superadmin.subscriptions.index')} className="rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Cancel</Link>
                    <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {processing ? 'Saving...' : 'Save subscription'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
