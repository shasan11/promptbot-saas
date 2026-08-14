import GoogleButton from '@/Components/Auth/GoogleButton';
import Money from '@/Components/Portal/Money';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register({ selectedPlan, interval, googleAuth = null }) {
    const { data, setData, post, processing, errors } = useForm({ name: '', email: '', password: '', password_confirmation: '', account_name: '', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone, plan: selectedPlan?.slug || '', interval });
    const submit = (event) => { event.preventDefault(); post(route('portal.register.store')); };
    const input = 'mt-1.5 w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
    const field = 'block text-sm font-medium text-slate-700';

    return <GuestLayout><Head title="Create account" /><form onSubmit={submit}>
        <div className="text-center">
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">Customer portal</p>
            <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-950">Create account</h1>
            <p className="mt-2 text-sm text-slate-500">Set up your account in a minute.</p>
        </div>

        {selectedPlan && <div className="mt-5 flex items-center justify-between gap-4 rounded-lg bg-slate-50 px-4 py-3 text-sm"><div><p className="font-semibold text-slate-900">{selectedPlan.name}</p><p className="text-xs text-slate-500">{interval === 'yearly' ? 'Yearly billing' : 'Monthly billing'}</p></div><p className="font-bold text-slate-950"><Money value={interval === 'yearly' ? selectedPlan.annual_price : selectedPlan.monthly_price} currency={selectedPlan.currency} /></p></div>}

        {googleAuth?.enabled && <div className="mt-5"><GoogleButton href={googleAuth.url} /><div className="my-5 flex items-center gap-3" aria-hidden="true"><span className="h-px flex-1 bg-slate-200" /><span className="text-xs text-slate-400">or</span><span className="h-px flex-1 bg-slate-200" /></div></div>}

        <div className={`${googleAuth?.enabled ? '' : 'mt-6'} space-y-4`}>
            <label className={field}>Name<input className={input} value={data.name} autoComplete="name" onChange={e => setData('name', e.target.value)} required />{errors.name && <span className="mt-1 block text-xs text-rose-600">{errors.name}</span>}</label>
            <label className={field}>Work email<input type="email" className={input} value={data.email} autoComplete="email" onChange={e => setData('email', e.target.value)} required />{errors.email && <span className="mt-1 block text-xs text-rose-600">{errors.email}</span>}</label>
            <label className={field}>Company or account<input className={input} value={data.account_name} autoComplete="organization" onChange={e => setData('account_name', e.target.value)} required />{errors.account_name && <span className="mt-1 block text-xs text-rose-600">{errors.account_name}</span>}</label>
            <div className="grid gap-4 sm:grid-cols-2">
                <label className={field}>Password<input type="password" className={input} value={data.password} autoComplete="new-password" onChange={e => setData('password', e.target.value)} required />{errors.password && <span className="mt-1 block text-xs text-rose-600">{errors.password}</span>}</label>
                <label className={field}>Confirm<input type="password" className={input} value={data.password_confirmation} autoComplete="new-password" onChange={e => setData('password_confirmation', e.target.value)} required /></label>
            </div>
        </div>

        <button disabled={processing} className="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-50">{processing ? 'Creating account…' : 'Create account'}</button>
        <p className="mt-5 text-center text-sm text-slate-500">Already have an account? <Link href={route('portal.login')} className="font-semibold text-indigo-700 hover:text-indigo-500">Sign in</Link></p>
    </form></GuestLayout>;
}
