import Badge from '@/Components/UI/Badge';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, History, Info } from 'lucide-react';

function StatTile({ label, value }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
            <div className="text-sm font-medium text-slate-500">{label}</div>
            <div className="mt-2 text-3xl font-bold tracking-tight text-slate-900">{value}</div>
        </div>
    );
}

export default function Overview({ stats, checks, recentActivity }) {
    return (
        <AdministrationShell title="Administration overview" description="A summary of your workspace's people, access, and administrative health.">
            <Head title="Administration" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile label="Active users" value={stats.activeUsers} />
                <StatTile label="Pending invitations" value={stats.pendingInvitations} />
                <StatTile label="Teams" value={stats.teams} />
                <StatTile label="Departments" value={stats.departments} />
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-x-8 gap-y-2 rounded-md border border-slate-200 bg-white px-5 py-3 text-sm text-slate-600 shadow-soft">
                <span><span className="font-semibold text-slate-900">{stats.suspendedUsers}</span> suspended users</span>
                <span><span className="font-semibold text-slate-900">{stats.customRoles}</span> roles</span>
                <span><span className="font-semibold text-slate-900">{stats.usersWithoutRoles}</span> users without a role</span>
            </div>

            {checks.length > 0 && (
                <SectionCard className="mt-6" title="Needs attention">
                    <div className="grid gap-3 sm:grid-cols-2">
                        {checks.map((check) => (
                            <Link
                                key={check.title}
                                href={route(check.route)}
                                className={`flex items-start gap-3 rounded-md border p-4 text-sm hover:shadow-soft ${check.severity === 'warning' ? 'border-amber-200 bg-amber-50/40' : 'border-blue-200 bg-blue-50/40'}`}
                            >
                                {check.severity === 'warning' ? <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" /> : <Info className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />}
                                <div>
                                    <div className="flex items-center gap-2 font-semibold text-slate-800">{check.title} <Badge tone={check.severity === 'warning' ? 'warning' : 'info'}>{check.count}</Badge></div>
                                    <p className="mt-1 text-xs text-slate-500">{check.description}</p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </SectionCard>
            )}

            <SectionCard className="mt-6" title="Recent administrative activity">
                {recentActivity.length ? (
                    <ul className="space-y-3">
                        {recentActivity.map((entry) => (
                            <li key={entry.id} className="flex items-start gap-3 text-sm">
                                <History className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                <div>
                                    <p className="text-slate-700">{entry.description || entry.event}</p>
                                    <p className="text-xs text-slate-400">{entry.actor?.name || entry.actor_name || 'System'} · {new Date(entry.created_at).toLocaleString()}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <EmptyState icon={History} title="No administrative activity yet" description="Important workspace changes will appear here." />
                )}
            </SectionCard>
        </AdministrationShell>
    );
}
