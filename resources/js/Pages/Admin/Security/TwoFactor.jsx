import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function TwoFactor({ confirmed, required, provisioningUri, recoveryCodesRemaining }) {
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({ password: '' });
    const recoveryForm = useForm({});

    return (
        <AuthenticatedLayout header={<PageHeader title="Two-Factor Authentication" subtitle="Protect platform administrator accounts with TOTP and recovery codes." />}>
            <Head title="Two-Factor Authentication" />
            <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
                <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="text-sm font-semibold text-slate-600">Status</div>
                    <div className="mt-1 text-2xl font-bold text-slate-950">{confirmed ? 'Enabled' : 'Pending confirmation'}</div>
                    <div className="mt-2 text-sm text-slate-500">{required ? 'This account is required to use 2FA.' : '2FA is optional for this account.'}</div>
                    {!confirmed && (
                        <form className="mt-6 space-y-4" onSubmit={(event) => { event.preventDefault(); confirmForm.post(route('superadmin.security.two-factor.confirm')); }}>
                            <div className="rounded-md bg-slate-50 p-3 text-xs text-slate-600 break-all">{provisioningUri}</div>
                            <input className="rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" value={confirmForm.data.code} onChange={(event) => confirmForm.setData('code', event.target.value)} placeholder="123456" />
                            <button className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" type="submit">Confirm 2FA</button>
                        </form>
                    )}
                </section>
                <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-base font-bold text-slate-950">Recovery Codes</h2>
                    <p className="mt-2 text-sm text-slate-500">{recoveryCodesRemaining} recovery codes remain.</p>
                    <button className="mt-4 rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" onClick={() => recoveryForm.post(route('superadmin.security.recovery-codes.regenerate'))} type="button">Regenerate</button>
                    {confirmed && !required && (
                        <form className="mt-6 space-y-3" onSubmit={(event) => { event.preventDefault(); disableForm.delete(route('superadmin.security.two-factor.destroy')); }}>
                            <input className="w-full rounded-md border-slate-300 text-sm shadow-sm" type="password" value={disableForm.data.password} onChange={(event) => disableForm.setData('password', event.target.value)} placeholder="Password confirmation" />
                            <button className="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white" type="submit">Disable</button>
                        </form>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
