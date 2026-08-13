import Money from '@/Components/Portal/Money';
import Panel from '@/Components/Portal/Panel';
import PortalLayout from '@/Layouts/PortalLayout';
import { Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

export default function Create({
    plans,
    selection,
    tenantBaseDomain,
    paymentRequired = false,
    allowTrialWithoutPayment = false,
    defaultTrialDays = 0,
    defaultRegion = '',
    billingProfileReady = true,
    provisioningAvailable = true,
}) {
    const selected = plans.find((plan) => plan.slug === selection?.plan) || plans[0];
    const { data, setData, post, processing, errors } = useForm({
        workspace_name: '',
        slug: '',
        region: defaultRegion,
        plan_id: selected?.id || '',
        billing_interval: selection?.interval || 'monthly',
        create_tenant_owner: false,
        idempotency_key: crypto.randomUUID(),
    });
    const plan = useMemo(
        () => plans.find((item) => String(item.id) === String(data.plan_id)),
        [plans, data.plan_id],
    );
    const trialDays = Number(plan?.trial_days || defaultTrialDays || 0);
    const effectivePaymentRequired = paymentRequired && !(allowTrialWithoutPayment && trialDays > 0);
    const canSubmit = provisioningAvailable && (!effectivePaymentRequired || billingProfileReady);
    const submit = (event) => {
        event.preventDefault();
        if (canSubmit) post(route('portal.workspaces.store'));
    };
    const field = 'w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';

    return (
        <PortalLayout title="Create workspace">
            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[1.4fr_0.8fr]">
                <div className="space-y-6">
                    <Panel title="1. Workspace details" description="Choose the customer-facing name and unique PromptBot subdomain.">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="text-sm font-medium text-slate-700">
                                Workspace name
                                <input className={`${field} mt-1.5`} value={data.workspace_name} onChange={(event) => setData('workspace_name', event.target.value)} required />
                                {errors.workspace_name && <span className="mt-1 block text-xs text-rose-600">{errors.workspace_name}</span>}
                            </label>
                            <label className="text-sm font-medium text-slate-700">
                                Subdomain
                                <div className="mt-1.5 flex">
                                    <input className={`${field} rounded-r-none`} value={data.slug} onChange={(event) => setData('slug', event.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))} required />
                                    <span className="flex items-center rounded-r-lg border border-l-0 border-slate-300 bg-slate-50 px-3 text-xs text-slate-500">.{tenantBaseDomain}</span>
                                </div>
                                {errors.slug && <span className="mt-1 block text-xs text-rose-600">{errors.slug}</span>}
                            </label>
                            {(defaultRegion || data.region) && <label className="text-sm font-medium text-slate-700 sm:col-span-2">Region<input className={`${field} mt-1.5`} value={data.region} onChange={(event) => setData('region', event.target.value)} placeholder="Platform default" />{errors.region && <span className="mt-1 block text-xs text-rose-600">{errors.region}</span>}</label>}
                        </div>
                    </Panel>

                    <Panel title="2. Select a plan">
                        <div className="grid gap-3 sm:grid-cols-2">
                            {plans.map((item) => (
                                <label key={item.id} className={`cursor-pointer rounded-lg border p-4 ${String(data.plan_id) === String(item.id) ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'}`}>
                                    <input type="radio" className="sr-only" value={item.id} checked={String(data.plan_id) === String(item.id)} onChange={(event) => setData('plan_id', event.target.value)} />
                                    <div className="flex justify-between">
                                        <span className="font-semibold text-slate-900">{item.name}</span>
                                        {item.is_recommended && <span className="text-xs font-semibold text-indigo-600">Popular</span>}
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">{item.description}</p>
                                </label>
                            ))}
                        </div>
                        {errors.plan_id && <p className="mt-2 text-xs text-rose-600">{errors.plan_id}</p>}
                    </Panel>

                    <Panel title="3. Billing and workspace access">
                        <div className="grid gap-3 sm:grid-cols-2">
                            {['monthly', 'yearly'].map((interval) => (
                                <label key={interval} className={`cursor-pointer rounded-lg border p-4 capitalize ${data.billing_interval === interval ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'}`}>
                                    <input type="radio" className="mr-2" checked={data.billing_interval === interval} onChange={() => setData('billing_interval', interval)} />
                                    {interval}
                                </label>
                            ))}
                        </div>
                        <label className="mt-4 flex items-start gap-3 rounded-lg bg-slate-50 p-4 text-sm">
                            <input type="checkbox" className="mt-0.5 rounded" checked={data.create_tenant_owner} onChange={(event) => setData('create_tenant_owner', event.target.checked)} />
                            <span>
                                <strong className="block text-slate-800">Invite me into this workspace</strong>
                                <span className="text-slate-500">Creates a separate workspace staff identity. Portal account access alone only manages the service.</span>
                            </span>
                        </label>
                    </Panel>
                </div>

                <aside>
                    <Panel title="Billing summary" className="sticky top-24">
                        <div className="space-y-3 text-sm">
                            <div className="flex justify-between"><span className="text-slate-500">Plan</span><span className="font-semibold">{plan?.name || 'Select a plan'}</span></div>
                            <div className="flex justify-between"><span className="text-slate-500">Interval</span><span className="capitalize">{data.billing_interval}</span></div>
                            <div className="border-t border-slate-200 pt-3">
                                <div className="flex items-end justify-between">
                                    <span className="font-semibold">Due each {data.billing_interval === 'yearly' ? 'year' : 'month'}</span>
                                    <span className="text-xl font-bold"><Money value={data.billing_interval === 'yearly' ? plan?.annual_price : plan?.monthly_price} currency={plan?.currency || 'USD'} /></span>
                                </div>
                            </div>
                        </div>

                        {effectivePaymentRequired && !billingProfileReady && (
                            <div className="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                Add your legal name and billing address before continuing to payment.
                                <Link href={route('portal.billing.profile')} className="mt-2 block font-semibold underline">Complete billing profile</Link>
                            </div>
                        )}
                        {!provisioningAvailable && (
                            <div className="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                Self-service setup is temporarily unavailable because automatic workspace provisioning is not configured. Please contact platform support.
                            </div>
                        )}

                        <button disabled={processing || !plan || !canSubmit} className="mt-5 w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">
                            {processing ? 'Processing…' : effectivePaymentRequired ? 'Continue to payment' : 'Confirm and create'}
                        </button>
                        <p className="mt-3 text-xs leading-5 text-slate-500">
                            {effectivePaymentRequired
                                ? 'Your workspace is provisioned only after its invoice is paid. Duplicate submissions are safely ignored.'
                                : trialDays > 0
                                    ? `This plan starts with a ${trialDays}-day trial. Provisioning progress is recorded and duplicate submissions are safely ignored.`
                                    : 'Provisioning progress is recorded by the platform. A duplicate submission will not create a second workspace.'}
                        </p>
                    </Panel>
                </aside>
            </form>
        </PortalLayout>
    );
}
