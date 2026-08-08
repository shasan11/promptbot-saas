import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Select from '@/Components/UI/Select';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, router } from '@inertiajs/react';
import { Users as UsersIcon, X } from 'lucide-react';
import { useState } from 'react';

export default function Show({ team, availableUsers }) {
    const [selected, setSelected] = useState('');

    const addMember = () => {
        if (!selected) return;
        router.post(route('tenant.admin.administration.teams.members.store', team.id), { user_id: selected }, { preserveScroll: true, onSuccess: () => setSelected('') });
    };

    return (
        <AdministrationShell
            title={team.name}
            description={team.description}
            actions={<Button href={route('tenant.admin.administration.teams.edit', team.id)} variant="secondary">Edit team</Button>}
        >
            <Head title={team.name} />

            <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                <div className="flex flex-wrap gap-x-8 gap-y-2 text-sm text-slate-600">
                    <span>Lead: <span className="font-medium text-slate-900">{team.lead?.name || 'Unassigned'}</span></span>
                    <span>Department: <span className="font-medium text-slate-900">{team.department?.name || 'None'}</span></span>
                    <span>Status: <Badge tone={team.status === 'active' ? 'brand' : 'neutral'}>{team.status}</Badge></span>
                </div>
            </div>

            <SectionCard className="mt-6" title="Members" actions={(
                <div className="flex gap-2">
                    <Select value={selected} onChange={(e) => setSelected(e.target.value)} className="w-56">
                        <option value="">Add a member…</option>
                        {availableUsers.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                    </Select>
                    <Button size="sm" variant="brand" onClick={addMember} disabled={!selected}>Add</Button>
                </div>
            )}>
                {team.members.length ? (
                    <div className="divide-y divide-slate-100">
                        {team.members.map((member) => (
                            <div key={member.id} className="flex items-center justify-between py-3">
                                <div className="flex items-center gap-2.5">
                                    <Avatar name={member.name} size="sm" />
                                    <div>
                                        <div className="font-medium text-slate-900">{member.name}</div>
                                        <div className="text-xs text-slate-500">{member.email}</div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => router.delete(route('tenant.admin.administration.teams.members.destroy', [team.id, member.id]), { preserveScroll: true })}
                                    className="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                    aria-label={`Remove ${member.name}`}
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState icon={UsersIcon} title="No members yet" description="Add users to this team above." />
                )}
            </SectionCard>
        </AdministrationShell>
    );
}
