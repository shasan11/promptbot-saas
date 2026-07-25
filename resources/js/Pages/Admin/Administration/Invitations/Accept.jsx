import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Accept({ token, email, role }) {
    const form = useForm({ name: '', password: '', password_confirmation: '' });

    return (
        <GuestLayout>
            <Head title="Accept Invitation" />
            <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); form.post(route('superadmin.invitations.accept.store', token)); }}>
                <div>
                    <h1 className="text-xl font-bold text-slate-950">Accept administrator invitation</h1>
                    <p className="mt-1 text-sm text-slate-500">{email} · {role}</p>
                </div>
                <input className="w-full rounded-md border-slate-300 text-sm shadow-sm" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Full name" />
                <input className="w-full rounded-md border-slate-300 text-sm shadow-sm" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} placeholder="Password" />
                <input className="w-full rounded-md border-slate-300 text-sm shadow-sm" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} placeholder="Confirm password" />
                <button className="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" type="submit">Accept invitation</button>
            </form>
        </GuestLayout>
    );
}
