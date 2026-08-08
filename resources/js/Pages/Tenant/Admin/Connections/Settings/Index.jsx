import { SectionCard } from '@/Components/UI/Card';
import Badge from '@/Components/UI/Badge';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head } from '@inertiajs/react';

export default function Index({ defaults }) {
    return (
        <ConnectionsShell title="Connection settings" description="Workspace defaults for sync safety, retention, database writes, approvals, and secret handling.">
            <Head title="Connection settings" />
            <SectionCard title="Default controls">
                <dl className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {Object.entries(defaults).map(([key, value]) => (
                        <div key={key} className="rounded-md border border-slate-200 p-4">
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{key.replaceAll('_', ' ')}</dt>
                            <dd className="mt-2">{typeof value === 'boolean' ? <Badge tone={value ? 'brand' : 'neutral'}>{value ? 'Enabled' : 'Disabled'}</Badge> : <span className="text-sm font-semibold text-slate-800">{String(value).replaceAll('_', ' ')}</span>}</dd>
                        </div>
                    ))}
                </dl>
            </SectionCard>
        </ConnectionsShell>
    );
}
