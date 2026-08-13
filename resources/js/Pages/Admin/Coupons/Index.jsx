import PageHeader from '@/Components/Superadmin/PageHeader';
import { SectionCard } from '@/Components/UI/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

function CouponForm({ coupon = null, plans }) {
    const meta = coupon?.metadata || {};
    const form = useForm({
        code: coupon?.code || '', name: coupon?.name || '', type: coupon?.type || 'percent', value: coupon?.value || '',
        currency: meta.currency || 'USD', duration: meta.duration || 'once', duration_months: meta.duration_months || '',
        max_redemptions: coupon?.max_redemptions || '', per_account_limit: coupon?.per_account_limit || '',
        billing_interval: coupon?.billing_interval || '', minimum_amount: coupon?.minimum_amount || '',
        starts_at: coupon?.starts_at || '', expires_at: coupon?.expires_at || '', is_active: coupon ? !!coupon.is_active : true,
        plan_ids: coupon?.plans?.map(plan => plan.id) || [],
    });
    const togglePlan = id => form.setData('plan_ids', form.data.plan_ids.includes(id) ? form.data.plan_ids.filter(value => value !== id) : [...form.data.plan_ids, id]);
    const submit = event => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { if (!coupon) form.reset(); } };
        coupon ? form.put(route('superadmin.coupons.update', coupon.id), options) : form.post(route('superadmin.coupons.store'), options);
    };

    return <SectionCard title={coupon ? `${coupon.code} · ${coupon.redemptions} redemption(s)` : 'Create coupon'}><form onSubmit={submit} className="space-y-3">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><input required placeholder="Code" value={form.data.code} onChange={event => form.setData('code', event.target.value.toUpperCase())} className="rounded-lg border-slate-300" /><input required placeholder="Campaign name" value={form.data.name} onChange={event => form.setData('name', event.target.value)} className="rounded-lg border-slate-300" /><select value={form.data.type} onChange={event => form.setData('type', event.target.value)} className="rounded-lg border-slate-300"><option value="percent">Percent</option><option value="fixed">Fixed amount</option></select><div className="flex"><input required type="number" min="0.01" step="0.01" value={form.data.value} onChange={event => form.setData('value', event.target.value)} className="min-w-0 flex-1 rounded-l-lg border-slate-300" />{form.data.type === 'fixed' && <input value={form.data.currency} onChange={event => form.setData('currency', event.target.value.toUpperCase())} className="w-20 rounded-r-lg border-slate-300" />}</div></div>
        <div className="grid gap-3 sm:grid-cols-4"><select value={form.data.duration} onChange={event => form.setData('duration', event.target.value)} className="rounded-lg border-slate-300"><option value="once">Once</option><option value="forever">Forever</option><option value="repeating">Repeating months</option></select><input type="number" min="1" disabled={form.data.duration !== 'repeating'} placeholder="Months" value={form.data.duration_months} onChange={event => form.setData('duration_months', event.target.value)} className="rounded-lg border-slate-300" /><input type="number" min="1" placeholder="Max redemptions" value={form.data.max_redemptions} onChange={event => form.setData('max_redemptions', event.target.value)} className="rounded-lg border-slate-300" /><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={event => form.setData('is_active', event.target.checked)} className="rounded" />Active</label></div>
        <div className="grid gap-3 sm:grid-cols-3"><input type="number" min="1" placeholder="Per-account limit" value={form.data.per_account_limit} onChange={event => form.setData('per_account_limit', event.target.value)} className="rounded-lg border-slate-300" /><select value={form.data.billing_interval} onChange={event => form.setData('billing_interval', event.target.value)} className="rounded-lg border-slate-300"><option value="">Any billing interval</option><option value="monthly">Monthly only</option><option value="yearly">Yearly only</option></select><input type="number" min="0" step="0.01" placeholder="Minimum purchase" value={form.data.minimum_amount} onChange={event => form.setData('minimum_amount', event.target.value)} className="rounded-lg border-slate-300" /></div>
        <div className="grid gap-3 sm:grid-cols-2"><label className="text-sm">Starts<input type="datetime-local" value={form.data.starts_at || ''} onChange={event => form.setData('starts_at', event.target.value)} className="mt-1 w-full rounded-lg border-slate-300" /></label><label className="text-sm">Expires<input type="datetime-local" value={form.data.expires_at || ''} onChange={event => form.setData('expires_at', event.target.value)} className="mt-1 w-full rounded-lg border-slate-300" /></label></div>
        <div><p className="text-sm font-semibold">Eligible plans <span className="font-normal text-slate-500">(none means all)</span></p><div className="mt-2 flex flex-wrap gap-3">{plans.map(plan => <label key={plan.id} className="flex items-center gap-2 text-sm"><input type="checkbox" className="rounded" checked={form.data.plan_ids.includes(plan.id)} onChange={() => togglePlan(plan.id)} />{plan.name}</label>)}</div></div>
        {Object.values(form.errors).map(error => <p key={error} className="text-sm text-rose-600">{error}</p>)}
        <div className="flex justify-end gap-3">{coupon && coupon.is_active && <button type="button" onClick={() => router.delete(route('superadmin.coupons.destroy', coupon.id))} className="text-sm font-semibold text-rose-600">Archive</button>}<button className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{coupon ? 'Save coupon' : 'Create coupon'}</button></div>
    </form></SectionCard>;
}

export default function Index({ coupons, plans }) {
    return <AuthenticatedLayout header={<PageHeader title="Coupons" subtitle="Manage plan, interval, purchase, redemption, and duration eligibility." />}><Head title="Coupons" /><div className="space-y-5"><CouponForm plans={plans} />{coupons.data.map(coupon => <CouponForm key={coupon.id} coupon={coupon} plans={plans} />)}</div></AuthenticatedLayout>;
}
