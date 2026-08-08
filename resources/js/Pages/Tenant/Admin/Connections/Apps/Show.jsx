import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import { CapabilityList, StatusBadge } from '@/Components/Tenant/Connections/ConnectionBadges';
import ConnectionsShell from '@/Components/Tenant/Connections/ConnectionsShell';
import { Head, Link } from '@inertiajs/react';
import { Plus, ShieldCheck } from 'lucide-react';

export default function Show({ integration }) {
    return (
        <ConnectionsShell
            title={integration.name}
            description={integration.description}
            actions={<Button href={route('tenant.admin.connections.create', { integration: integration.id })} variant="brand" icon={Plus}>Add connection</Button>}
        >
            <Head title={integration.name} />
            <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                <SectionCard title="Capabilities and support">
                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Authentication</p>
                            <p className="mt-2 text-sm text-slate-700">{integration.auth_methods.join(', ')}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Supported resources</p>
                            <p className="mt-2 text-sm text-slate-700">{(integration.resource_types || []).join(', ')}</p>
                        </div>
                        <div className="md:col-span-2">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Capabilities</p>
                            <CapabilityList capabilities={integration.capabilities || []} limit={20} />
                        </div>
                    </div>
                </SectionCard>

                <SectionCard title="Enterprise readiness">
                    <ul className="space-y-3 text-sm text-slate-600">
                        {['Least-privilege scopes', 'Tenant-isolated credentials', 'Health checks', 'Sync and webhook logs', 'AI action gating'].map((item) => (
                            <li key={item} className="flex items-center gap-2"><ShieldCheck className="h-4 w-4 text-brand-600" /> {item}</li>
                        ))}
                    </ul>
                </SectionCard>
            </div>

            <SectionCard className="mt-6" title="Existing connections">
                {integration.connections.length ? (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <tbody className="divide-y divide-slate-100">
                                {integration.connections.map((connection) => (
                                    <tr key={connection.id}>
                                        <td className="py-3 pr-4"><Link className="font-semibold text-brand-700" href={route('tenant.admin.connections.show', connection.id)}>{connection.name}</Link></td>
                                        <td className="py-3"><StatusBadge value={connection.status} /></td>
                                        <td className="py-3 text-right text-slate-500">{connection.provider_account_name}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : <p className="text-sm text-slate-500">No tenant connection has been created for this integration yet.</p>}
            </SectionCard>
        </ConnectionsShell>
    );
}
