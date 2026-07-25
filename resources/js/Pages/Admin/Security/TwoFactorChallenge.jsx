import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function TwoFactorChallenge() {
    const form = useForm({ code: '', recovery_code: '' });

    return (
        <GuestLayout>
            <Head title="Two-Factor Challenge" />
            <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); form.post(route('superadmin.two-factor.challenge.verify')); }}>
                <div>
                    <h1 className="text-xl font-bold text-slate-950">Two-factor challenge</h1>
                    <p className="mt-1 text-sm text-slate-500">Enter an authentication code or a recovery code.</p>
                </div>
                <input className="w-full rounded-md border-slate-300 text-sm shadow-sm" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} placeholder="Authentication code" />
                <input className="w-full rounded-md border-slate-300 text-sm shadow-sm" value={form.data.recovery_code} onChange={(event) => form.setData('recovery_code', event.target.value)} placeholder="Recovery code" />
                <button className="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" type="submit">Continue</button>
            </form>
        </GuestLayout>
    );
}
