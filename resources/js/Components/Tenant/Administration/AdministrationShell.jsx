import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, usePage } from '@inertiajs/react';
import {
    Building2, Database, Layers, LayoutGrid, Shield, UserPlus, Users,
} from 'lucide-react';

const SECTIONS = [
    {
        label: 'People & access',
        items: [
            { label: 'Overview', route: 'tenant.admin.administration.index', pattern: 'tenant.admin.administration.index', icon: LayoutGrid, permission: 'users.view' },
            { label: 'Users', route: 'tenant.admin.administration.users.index', pattern: 'tenant.admin.administration.users.*', icon: Users, permission: 'users.view' },
            { label: 'Invitations', route: 'tenant.admin.administration.invitations.index', pattern: 'tenant.admin.administration.invitations.*', icon: UserPlus, permission: 'invitations.view' },
            { label: 'Teams', route: 'tenant.admin.administration.teams.index', pattern: 'tenant.admin.administration.teams.*', icon: Layers, permission: 'teams.view' },
            { label: 'Departments', route: 'tenant.admin.administration.departments.index', pattern: 'tenant.admin.administration.departments.*', icon: Building2, permission: 'departments.view' },
            { label: 'Roles & permissions', route: 'tenant.admin.administration.roles.index', pattern: 'tenant.admin.administration.roles.*', icon: Shield, permission: 'roles.view' },
        ],
    },
];

export default function AdministrationShell({ title, description, actions, children }) {
    const { auth } = usePage().props;
    const can = (permission) => !permission || auth?.permissions?.includes(permission);
    const sections = SECTIONS.map((section) => ({ ...section, items: section.items.filter((item) => can(item.permission)) })).filter((section) => section.items.length);
    const flatItems = sections.flatMap((section) => section.items);

    return (
        <AuthenticatedLayout title="Administration">
            <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
                <div className="lg:hidden">
                    <Select value={flatItems.find((item) => route().current(item.pattern))?.route || ''} onChange={(event) => router_visit(event.target.value)}>
                        {sections.map((section) => (
                            <optgroup key={section.label} label={section.label}>
                                {section.items.map((item) => <option key={item.route} value={item.route}>{item.label}</option>)}
                            </optgroup>
                        ))}
                    </Select>
                </div>

                <nav className="hidden space-y-5 lg:block" aria-label="Administration navigation">
                    {sections.map((section) => (
                        <div key={section.label}>
                            <p className="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{section.label}</p>
                            <div className="space-y-0.5">
                                {section.items.map((item) => {
                                    const Icon = item.icon;
                                    const isActive = route().current(item.pattern);
                                    return (
                                        <Link
                                            key={item.route}
                                            href={route(item.route)}
                                            aria-current={isActive ? 'page' : undefined}
                                            className={`flex h-9 items-center gap-2.5 rounded-md px-2.5 text-sm font-medium transition-colors ${
                                                isActive ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-100 hover:text-navy-900'
                                            }`}
                                        >
                                            <Icon className={`h-4 w-4 ${isActive ? 'text-brand-600' : 'text-slate-400'}`} strokeWidth={1.8} aria-hidden="true" />
                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </nav>

                <div className="min-w-0">
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h1 className="text-xl font-bold tracking-tight text-slate-900">{title}</h1>
                            {description && <p className="mt-1 max-w-2xl text-sm text-slate-500">{description}</p>}
                        </div>
                        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                    </div>
                    {children}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function router_visit(routeName) {
    if (!routeName) return;
    window.location.assign(route(routeName));
}
