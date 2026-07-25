import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ roles = [] }) {
    const form = useForm({ email: '', role_id: roles[0]?.id || '' });

    return (
        <AuthenticatedLayout header={<PageHeader title="Invite Administrator" subtitle="Send a time-limited platform administrator invitation." />}>
            <Head title="Invite Administrator" />
            <form className="max-w-2xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" onSubmit={(event) => { event.preventDefault(); form.post(route('superadmin.administrators.invitations.store')); }}>
                <input className="w-full rounded-md border-slate-300 text-sm shadow-sm" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} placeholder="email@example.com" />
                <select className="w-full rounded-md border-slate-300 text-sm shadow-sm" value={form.data.role_id} onChange={(event) => form.setData('role_id', event.target.value)}>
                    {roles.map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}
                </select>
                <button className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" type="submit">Create invitation</button>
            </form>
        </AuthenticatedLayout>
    );
}
