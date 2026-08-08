import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import EmptyState from '@/Components/UI/EmptyState';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, router, usePage } from '@inertiajs/react';
import { Clock, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function summarize(intervals) {
    if (!intervals.length) return 'No working intervals';
    const byDay = {};
    intervals.forEach((i) => { byDay[i.day_of_week] = (byDay[i.day_of_week] || []).concat(`${i.starts_at.slice(0, 5)}–${i.ends_at.slice(0, 5)}`); });
    return Object.entries(byDay).map(([day, times]) => `${DAYS[day].slice(0, 3)} ${times.join(', ')}`).join(' · ');
}

export default function Index({ policies }) {
    const { auth } = usePage().props;
    const canCreate = auth?.permissions?.includes('workspace.manage_business_hours');
    const [deleting, setDeleting] = useState(null);

    return (
        <AdministrationShell
            title="Business hours"
            description="Reusable working-hour policies for teams and SLA calculations."
            actions={canCreate && <Button href={route('tenant.admin.administration.business-hours.create')} variant="brand" icon={Plus}>Create policy</Button>}
        >
            <Head title="Business hours" />

            {policies.length ? (
                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
                    <div className="divide-y divide-slate-100">
                        {policies.map((policy) => (
                            <div key={policy.id} className="flex items-center justify-between gap-4 px-5 py-4">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2 font-medium text-slate-900">
                                        {policy.name}
                                        {policy.is_default && <Badge tone="info">Default</Badge>}
                                        <Badge tone={policy.status === 'active' ? 'brand' : 'neutral'}>{policy.status}</Badge>
                                    </div>
                                    <p className="mt-0.5 truncate text-xs text-slate-500">{policy.timezone} · {summarize(policy.intervals)}</p>
                                </div>
                                <DropdownMenu
                                    items={[
                                        { label: 'Edit', icon: Pencil, onClick: () => router.visit(route('tenant.admin.administration.business-hours.edit', policy.id)) },
                                        ...(policy.is_default ? [] : [{ label: 'Delete', icon: Trash2, danger: true, onClick: () => setDeleting(policy) }]),
                                    ]}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            ) : (
                <EmptyState icon={Clock} title="No business hours configured" description="Create a policy to define when your team is available." action={canCreate && <Button href={route('tenant.admin.administration.business-hours.create')} variant="brand" icon={Plus}>Create policy</Button>} />
            )}

            <ConfirmDialog
                open={!!deleting}
                title={`Delete "${deleting?.name}"?`}
                variant="danger"
                confirmLabel="Delete"
                onCancel={() => setDeleting(null)}
                onConfirm={() => { router.delete(route('tenant.admin.administration.business-hours.destroy', deleting.id), { preserveScroll: true }); setDeleting(null); }}
            >
                Teams currently assigned this policy will need a new one.
            </ConfirmDialog>
        </AdministrationShell>
    );
}
