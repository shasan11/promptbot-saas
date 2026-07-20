import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

function Detail({ label, value, children }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</div>
            <div className="mt-2 text-sm font-semibold text-slate-950">{children || value || '-'}</div>
        </div>
    );
}

export default function Show({ subscription }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Subscription"
                    subtitle={`${subscription.tenant?.company_name || 'Tenant'} on ${subscription.plan?.name || 'unknown plan'}`}
                />
            }
        >
            <Head title="Subscription" />
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Detail label="Tenant" value={subscription.tenant?.company_name} />
                <Detail label="Plan" value={subscription.plan?.name} />
                <Detail label="Status"><StatusBadge status={subscription.status} /></Detail>
                <Detail label="Billing interval" value={subscription.billing_interval} />
                <Detail label="Starts at" value={subscription.starts_at} />
                <Detail label="Trial ends" value={subscription.trial_ends_at} />
                <Detail label="Ends at" value={subscription.ends_at} />
                <Detail label="Identifier" value={subscription.public_uuid || subscription.id} />
            </div>
        </AuthenticatedLayout>
    );
}
