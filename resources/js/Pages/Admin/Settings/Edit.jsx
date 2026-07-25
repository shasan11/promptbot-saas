import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function SettingsEdit({ group, groups = [], settings = {} }) {
    const form = useForm({ settings, reason: '' });

    return (
        <AuthenticatedLayout header={<PageHeader title="Platform Settings" subtitle={`Editing ${group} settings.`} />}>
            <Head title="Platform Settings" />
            <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
                <nav className="space-y-1">{groups.map((item) => <Link key={item} href={route('superadmin.settings.edit', item)} className={`block rounded-md px-3 py-2 text-sm font-semibold ${item === group ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'}`}>{item}</Link>)}</nav>
                <form className="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" onSubmit={(event) => { event.preventDefault(); form.put(route('superadmin.settings.update', group)); }}>
                    {Object.entries(form.data.settings).map(([key, value]) => (
                        <label key={key} className="block">
                            <span className="text-sm font-semibold text-slate-700">{key.replaceAll('_', ' ')}</span>
                            <input className="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm" value={value ?? ''} onChange={(event) => form.setData('settings', { ...form.data.settings, [key]: event.target.value })} />
                        </label>
                    ))}
                    {!Object.keys(form.data.settings).length && <div className="text-sm text-slate-500">No settings exist in this group yet.</div>}
                    <textarea className="w-full rounded-md border-slate-300 text-sm shadow-sm" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} placeholder="Reason for change" />
                    <button className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" type="submit">Save settings</button>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
