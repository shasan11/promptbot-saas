import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function AdministratorShow({ administrator, roles = [] }) {
    return (
        <AuthenticatedLayout header={<PageHeader title={administrator.name} subtitle={administrator.email} />}>
            <Head title={administrator.name} />
            <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <dl className="grid gap-4 sm:grid-cols-2">
                    {['id', 'role', 'is_active', 'two_factor_required', 'two_factor_confirmed_at', 'last_login_at'].map((key) => (
                        <div key={key}>
                            <dt className="text-xs font-bold uppercase text-slate-500">{key.replaceAll('_', ' ')}</dt>
                            <dd className="mt-1 text-sm font-semibold text-slate-900">{String(administrator[key] ?? '-')}</dd>
                        </div>
                    ))}
                </dl>
                <h2 className="mt-6 text-base font-bold text-slate-950">Available Roles</h2>
                <div className="mt-3 flex flex-wrap gap-2">{roles.map((role) => <span key={role.id} className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{role.name}</span>)}</div>
            </section>
        </AuthenticatedLayout>
    );
}
