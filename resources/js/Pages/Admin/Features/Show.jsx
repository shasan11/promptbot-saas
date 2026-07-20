import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ feature }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={feature.name}
                    subtitle={feature.description || 'Feature details and platform code.'}
                    actions={<Link href={route('superadmin.features.edit', feature.public_uuid || feature.id)} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Edit feature</Link>}
                />
            }
        >
            <Head title={feature.name} />
            <div className="grid gap-4 md:grid-cols-3">
                <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="text-xs font-bold uppercase tracking-wide text-slate-500">Code</div>
                    <div className="mt-2 font-mono text-sm font-semibold text-slate-950">{feature.code}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="text-xs font-bold uppercase tracking-wide text-slate-500">Type</div>
                    <div className="mt-2"><StatusBadge status={feature.type} /></div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="text-xs font-bold uppercase tracking-wide text-slate-500">Identifier</div>
                    <div className="mt-2 font-mono text-xs font-semibold text-slate-950">{feature.public_uuid || feature.id}</div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
