import Money from '@/Components/Portal/Money';
import Panel from '@/Components/Portal/Panel';
import StatusPill from '@/Components/Portal/StatusPill';
import PortalLayout from '@/Layouts/PortalLayout';
import { router, useForm } from '@inertiajs/react';

function SubscriptionCard({ item, plans, planChangePolicy, allowImmediateCancellation, allowPlanChanges, allowCancellations }) {
    const fixedTiming = ['immediate', 'period_end'].includes(planChangePolicy) ? planChangePolicy : null;
    const change = useForm({ plan_id: item.plan_id, billing_interval: item.billing_interval, timing: fixedTiming || 'period_end', reason: '' });
    const cancel = useForm({ immediate: false, reason: '', feedback: '' });
    const coupon = useForm({ code: '' });
    const pendingCancellation = !!item.cancel_at;

    return (
        <Panel title={item.tenant?.company_name || 'Service subscription'} description={`${item.plan?.name} · ${item.billing_interval}`}>
            <div className="grid gap-4 sm:grid-cols-4">
                <div><p className="text-xs uppercase text-slate-500">Status</p><StatusPill value={item.status} /></div>
                <div><p className="text-xs uppercase text-slate-500">Price</p><p className="font-semibold"><Money value={item.billing_interval === 'yearly' ? item.plan?.annual_price : item.plan?.monthly_price} currency={item.plan?.currency || 'USD'} /></p></div>
                <div><p className="text-xs uppercase text-slate-500">Current period</p><p className="text-sm">{item.current_period_ends_at ? `Until ${new Date(item.current_period_ends_at).toLocaleDateString()}` : '—'}</p></div>
                <div><p className="text-xs uppercase text-slate-500">Discount</p><p className="text-sm font-semibold">{item.coupon?.code || 'None'}</p></div>
            </div>
            {item.pending_plan && <div className="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Scheduled: {item.pending_plan.name} ({item.pending_billing_interval}) on {new Date(item.pending_change_effective_at).toLocaleDateString()}</div>}

            <details className="mt-5 border-t pt-4">
                <summary className="cursor-pointer text-sm font-semibold text-indigo-700">Manage subscription</summary>
                <div className="mt-4 grid gap-5 lg:grid-cols-3">
                    {allowPlanChanges ? <form onSubmit={(event) => { event.preventDefault(); change.put(route('portal.billing.subscriptions.change', item.public_uuid)); }} className="space-y-3">
                        <h3 className="font-semibold">Change plan</h3>
                        <select value={change.data.plan_id} onChange={(event) => change.setData('plan_id', event.target.value)} className="w-full rounded-lg border-slate-300">
                            {plans.map((plan) => <option key={plan.id} value={plan.id}>{plan.name}</option>)}
                        </select>
                        <select value={change.data.billing_interval} onChange={(event) => change.setData('billing_interval', event.target.value)} className="w-full rounded-lg border-slate-300"><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select>
                        {fixedTiming ? (
                            <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">Changes apply {fixedTiming === 'immediate' ? 'immediately' : 'at the end of the current period'} under the platform billing policy.</p>
                        ) : (
                            <select value={change.data.timing} onChange={(event) => change.setData('timing', event.target.value)} className="w-full rounded-lg border-slate-300"><option value="period_end">At period end</option><option value="immediate">Immediately</option></select>
                        )}
                        <input placeholder="Reason (optional)" value={change.data.reason} onChange={(event) => change.setData('reason', event.target.value)} className="w-full rounded-lg border-slate-300" />
                        <button className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Apply change</button>
                    </form> : <div><h3 className="font-semibold">Plan changes</h3><p className="mt-2 text-sm text-slate-500">Self-service plan changes are disabled by platform policy.</p></div>}

                    <div className="space-y-3">
                        <h3 className="font-semibold">Coupon</h3>
                        {item.coupon ? (
                            <><p className="text-sm text-emerald-700">{item.coupon.code} is active.</p><button onClick={() => router.delete(route('portal.billing.subscriptions.coupon.remove', item.public_uuid))} className="text-sm font-semibold text-rose-600">Remove coupon</button></>
                        ) : (
                            <form onSubmit={(event) => { event.preventDefault(); coupon.post(route('portal.billing.subscriptions.coupon', item.public_uuid)); }} className="space-y-3">
                                <input placeholder="Coupon code" value={coupon.data.code} onChange={(event) => coupon.setData('code', event.target.value.toUpperCase())} className="w-full rounded-lg border-slate-300" />
                                {coupon.errors.code && <p className="text-sm text-rose-600">{coupon.errors.code}</p>}
                                <button className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Apply coupon</button>
                            </form>
                        )}
                    </div>

                    <div className="space-y-3">
                        <h3 className="font-semibold">Cancellation</h3>
                        {!allowCancellations ? <p className="text-sm text-slate-500">Self-service cancellation is disabled by platform policy.</p> : pendingCancellation ? (
                            <><p className="text-sm text-amber-700">Cancellation scheduled for {new Date(item.cancel_at).toLocaleDateString()}.</p><button onClick={() => router.post(route('portal.billing.subscriptions.resume', item.public_uuid))} className="text-sm font-semibold text-indigo-700">Resume subscription</button></>
                        ) : (
                            <form onSubmit={(event) => { event.preventDefault(); cancel.post(route('portal.billing.subscriptions.cancel', item.public_uuid)); }} className="space-y-3">
                                <input required placeholder="Cancellation reason" value={cancel.data.reason} onChange={(event) => cancel.setData('reason', event.target.value)} className="w-full rounded-lg border-slate-300" />
                                <textarea placeholder="Feedback (optional)" value={cancel.data.feedback} onChange={(event) => cancel.setData('feedback', event.target.value)} className="w-full rounded-lg border-slate-300" />
                                {allowImmediateCancellation && <label className="flex gap-2 text-sm"><input type="checkbox" checked={cancel.data.immediate} onChange={(event) => cancel.setData('immediate', event.target.checked)} className="rounded" />Cancel immediately</label>}
                                <button className="text-sm font-semibold text-rose-600">Cancel subscription</button>
                            </form>
                        )}
                    </div>
                </div>
            </details>
        </Panel>
    );
}

export default function Subscriptions({ subscriptions, plans, planChangePolicy = 'customer_choice', allowImmediateCancellation = false, allowPlanChanges = true, allowCancellations = true }) {
    return (
        <PortalLayout title="Subscriptions">
            <div className="space-y-5">
                {subscriptions.data.map((item) => <SubscriptionCard key={item.id} item={item} plans={plans} planChangePolicy={planChangePolicy} allowImmediateCancellation={allowImmediateCancellation} allowPlanChanges={allowPlanChanges} allowCancellations={allowCancellations} />)}
                {!subscriptions.data.length && <Panel><p className="py-12 text-center text-sm text-slate-500">No subscriptions yet.</p></Panel>}
            </div>
        </PortalLayout>
    );
}
