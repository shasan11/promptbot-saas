import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, Link, usePage } from '@inertiajs/react';
import { Layers, Plus } from 'lucide-react';

export default function Index({ teams }) {
    const { auth } = usePage().props;
    const canCreate = auth?.permissions?.includes('teams.create');

    return (
        <AdministrationShell
            title="Teams"
            description="Organize users into functional groups for routing and ownership."
            actions={canCreate && <Button href={route('tenant.admin.administration.teams.create')} variant="brand" icon={Plus}>Create team</Button>}
        >
            <Head title="Teams" />

            {teams.length ? (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {teams.map((team) => (
                        <Link key={team.id} href={route('tenant.admin.administration.teams.show', team.id)} className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft transition hover:shadow-soft-lg">
                            <div className="flex items-center justify-between">
                                <span className="h-3 w-3 rounded-full" style={{ backgroundColor: team.color || '#64748B' }} />
                                <Badge tone={team.status === 'active' ? 'brand' : 'neutral'}>{team.status}</Badge>
                            </div>
                            <h3 className="mt-3 font-semibold text-slate-900">{team.name}</h3>
                            <p className="mt-1 text-xs text-slate-500">{team.members_count} member{team.members_count === 1 ? '' : 's'} · {team.department?.name || 'No department'}</p>
                            <p className="mt-2 text-xs text-slate-500">Lead: {team.lead?.name || 'Unassigned'}</p>
                        </Link>
                    ))}
                </div>
            ) : (
                <EmptyState icon={Layers} title="No teams have been created" description="Create teams to organize users, routing, and ownership." action={canCreate && <Button href={route('tenant.admin.administration.teams.create')} variant="brand" icon={Plus}>Create team</Button>} />
            )}
        </AdministrationShell>
    );
}
