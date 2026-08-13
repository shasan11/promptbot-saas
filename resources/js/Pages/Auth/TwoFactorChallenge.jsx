import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function TwoFactorChallenge() {
    const form = useForm({ code: '' });
    return <GuestLayout><Head title="Administrator two-factor authentication" /><div className="rounded-xl border bg-white p-8 shadow-sm"><h1 className="text-2xl font-bold text-slate-950">Two-factor authentication</h1><p className="mt-2 text-sm text-slate-600">Enter the six-digit code from your authenticator app, or one unused recovery code.</p><form onSubmit={event => { event.preventDefault(); form.post(route('two-factor.store')); }} className="mt-6 space-y-4"><input autoFocus autoComplete="one-time-code" required value={form.data.code} onChange={event => form.setData('code', event.target.value)} className="w-full rounded-lg border-slate-300 text-center font-mono text-xl tracking-widest" placeholder="000000" />{form.errors.code && <p className="text-sm text-rose-600">{form.errors.code}</p>}<button disabled={form.processing} className="w-full rounded-lg bg-indigo-600 px-4 py-3 font-semibold text-white">Verify and continue</button></form></div></GuestLayout>;
}
