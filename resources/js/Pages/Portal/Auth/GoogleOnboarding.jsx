import Money from '@/Components/Portal/Money';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function GoogleOnboarding({ identity, selectedPlan, interval }) {
    const { data, setData, post, processing, errors } = useForm({ account_name: `${identity.name}'s Account`, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone, plan: selectedPlan?.slug || '', interval });
    const submit = event => { event.preventDefault(); post(route('portal.oauth.google.complete')); };
    return <GuestLayout><Head title="Complete account setup" /><form onSubmit={submit}>
        <div className="flex items-center gap-3">{identity.avatar ? <img src={identity.avatar} alt="" referrerPolicy="no-referrer" className="h-11 w-11 rounded-full" /> : <span className="flex h-11 w-11 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-700">{identity.name?.[0]}</span>}<div><h1 className="text-xl font-bold text-slate-950">Complete your account</h1><p className="text-sm text-slate-500">Signed in as {identity.email}</p></div></div>
        <label className="mt-7 block text-sm font-medium text-slate-800">Company or account name<input className="mt-1.5 w-full rounded-lg border-slate-300" value={data.account_name} onChange={event => setData('account_name', event.target.value)} required />{errors.account_name && <span className="mt-1 block text-xs text-rose-600">{errors.account_name}</span>}</label>
        {selectedPlan && <div className="mt-5 rounded-xl bg-slate-50 p-4"><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Selected plan</p><div className="mt-2 flex items-center justify-between gap-4"><span className="font-bold">{selectedPlan.name}</span><span className="text-sm"><Money value={interval === 'yearly' ? selectedPlan.annual_price : selectedPlan.monthly_price} currency={selectedPlan.currency} />/{interval === 'yearly' ? 'year' : 'month'}</span></div></div>}
        <button disabled={processing} className="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{processing ? 'Creating account…' : 'Create account and continue'}</button>
    </form></GuestLayout>;
}
