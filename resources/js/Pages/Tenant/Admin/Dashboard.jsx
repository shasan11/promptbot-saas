import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Circle, Settings as SettingsIcon, Shield, Users as UsersIcon } from 'lucide-react';

function StatCard({ label, value, tone = 'slate' }) {
    const tones = {
        slate: 'bg-navy-800',
        emerald: 'bg-brand-600',
        blue: 'bg-blue-600',
    };

    return (
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
            <div className={`mb-5 h-1.5 w-12 rounded-full ${tones[tone] || tones.slate}`} />
            <div className="text-sm font-medium text-slate-500">{label}</div>
            <div className="mt-2 text-3xl font-bold tracking-tight text-slate-900">{value}</div>
        </div>
    );
}

export default function Dashboard({ tenant, stats = {}, recentUsers = [] }) {
    const hasSettingsConfigured = (stats.settings ?? 0) > 0;
    const hasRoles = (stats.roles ?? 0) > 0;
    const checklist = [
        { label: 'Workspace provisioned', done: true },
        { label: 'Company settings configured', done: hasSettingsConfigured, href: route('tenant.admin.settings.edit') },
        { label: 'Team roles available', done: hasRoles, href: route('tenant.admin.users.index') },
        { label: 'Team members invited', done: (stats.users ?? 0) > 1, href: route('tenant.admin.users.index') },
    ];
    const remaining = checklist.filter((item) => !item.done);

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="mb-6 flex items-center gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                <Avatar name={tenant.company_name} size="lg" />
                <div>
                    <h1 className="text-xl font-bold tracking-tight text-slate-900">Welcome back, {tenant.company_name}</h1>
                    <p className="mt-0.5 text-sm text-slate-500">Here's what's happening in your workspace.</p>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Users" value={stats.users ?? 0} tone="emerald" />
                <StatCard label="Roles" value={stats.roles ?? 0} tone="blue" />
                <StatCard label="Permissions" value={stats.permissions ?? 0} />
                <StatCard label="Configured settings" value={stats.settings ?? 0} />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
                <SectionCard
                    title="Recent users"
                    actions={<Button href={route('tenant.admin.users.index')} variant="ghost" size="sm">Manage users</Button>}
                >
                    {recentUsers.length ? (
                        <div className="divide-y divide-slate-100">
                            {recentUsers.map((user) => (
                                <div key={user.id} className="flex items-center gap-3 py-3">
                                    <Avatar name={user.name} size="sm" />
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium text-slate-900">{user.name}</div>
                                        <div className="truncate text-xs text-slate-500">{user.email}</div>
                                    </div>
                                    <div className="flex flex-wrap justify-end gap-1">
                                        {user.roles?.length ? user.roles.map((role) => <Badge key={role.id} tone="neutral">{role.label || role.name}</Badge>) : <span className="text-xs text-slate-400">No role</span>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState icon={UsersIcon} title="No users yet" description="Team members will appear here once they're added." />
                    )}
                </SectionCard>

                <div className="space-y-6">
                    <SectionCard title="Get set up" description={remaining.length ? `${remaining.length} step${remaining.length === 1 ? '' : 's'} remaining` : 'All set up'}>
                        <ul className="space-y-3 text-sm">
                            {checklist.map((item) => (
                                <li key={item.label}>
                                    {item.href && !item.done ? (
                                        <Link href={item.href} className="flex items-center gap-2 text-slate-700 hover:text-brand-700">
                                            <Circle className="h-4 w-4 shrink-0 text-slate-300" /> {item.label}
                                        </Link>
                                    ) : (
                                        <span className="flex items-center gap-2 text-slate-500">
                                            <CheckCircle2 className="h-4 w-4 shrink-0 text-brand-600" /> {item.label}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </SectionCard>

                    <SectionCard title="Quick actions">
                        <div className="space-y-2">
                            <Button href={route('tenant.admin.users.index')} variant="secondary" icon={UsersIcon} className="w-full justify-start">Manage users</Button>
                            <Button href={route('tenant.admin.settings.edit')} variant="secondary" icon={SettingsIcon} className="w-full justify-start">Workspace settings</Button>
                        </div>
                    </SectionCard>

                    {!hasSettingsConfigured && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            <p className="flex items-center gap-2 font-semibold"><Shield className="h-4 w-4" /> Finish workspace setup</p>
                            <p className="mt-1 text-xs text-amber-700/90">Company identity and mail sender settings aren't configured yet.</p>
                            <Button href={route('tenant.admin.settings.edit')} variant="secondary" size="sm" className="mt-3">Go to settings</Button>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
