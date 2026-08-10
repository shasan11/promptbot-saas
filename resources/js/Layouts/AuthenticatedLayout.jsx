import Alert from '@/Components/UI/Alert';
import Avatar from '@/Components/UI/Avatar';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import SuperadminLayout from '@/Layouts/SuperadminLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Bell, Building2, Cable, CalendarClock, CheckSquare, Code2, Gauge, Globe2, Inbox, LayoutDashboard, Library, LogOut, Menu, MessagesSquare, Search, ShieldCheck, Sparkles, Star, TicketCheck, User, UsersRound, Workflow, X } from 'lucide-react';
import { useState } from 'react';

const navigation = [
    { label: 'Dashboard', routeName: 'tenant.admin.dashboard', active: 'tenant.admin.dashboard', icon: LayoutDashboard },
    { label: 'Search', routeName: 'tenant.admin.search', active: 'tenant.admin.search', icon: Search },
    { label: 'Notifications', routeName: 'tenant.admin.notifications.index', active: 'tenant.admin.notifications.*', icon: Bell },
    { label: 'Inbox', routeName: 'tenant.admin.inbox.index', active: 'tenant.admin.inbox.*', icon: Inbox, permission: 'inbox.view' },
    { label: 'Tickets', routeName: 'tenant.admin.tickets.index', active: 'tenant.admin.tickets.*', icon: TicketCheck, permission: 'tickets.view' },
    { label: 'Tasks', routeName: 'tenant.admin.tasks.index', active: 'tenant.admin.tasks.*', icon: CheckSquare, permission: 'tasks.view' },
    { label: 'Operations', routeName: 'tenant.admin.operations.index', active: 'tenant.admin.operations.*', icon: Gauge, permission: 'operations.view' },
    { label: 'Automation', routeName: 'tenant.admin.automation.index', active: 'tenant.admin.automation.*', icon: Workflow, permission: 'automation.view' },
    { label: 'Experience', routeName: 'tenant.admin.experience.index', active: 'tenant.admin.experience.*', icon: Globe2, permission: 'experience.view' },
    { label: 'Reports', routeName: 'tenant.admin.reports.index', active: 'tenant.admin.reports.*', icon: BarChart3, permission: 'reports.view' },
    { label: 'Developer & security', routeName: 'tenant.admin.governance.index', active: 'tenant.admin.governance.*', icon: Code2, permission: 'governance.view' },
    { label: 'Quality', routeName: 'tenant.admin.quality.index', active: 'tenant.admin.quality.*', icon: Star, permission: 'quality.view' },
    { label: 'Workforce', routeName: 'tenant.admin.workforce.index', active: 'tenant.admin.workforce.*', icon: CalendarClock, permission: 'workforce.view' },
    { label: 'Contacts', routeName: 'tenant.admin.customers.contacts.index', active: 'tenant.admin.customers.contacts.*', icon: UsersRound, permission: 'customers.view' },
    { label: 'Companies', routeName: 'tenant.admin.customers.companies.index', active: 'tenant.admin.customers.companies.*', icon: Building2, permission: 'companies.view' },
    { label: 'Channels', routeName: 'tenant.admin.channels.index', active: 'tenant.admin.channels.*', icon: MessagesSquare, permission: 'channels.view' },
    { label: 'Knowledge base', routeName: 'tenant.admin.knowledge.index', active: 'tenant.admin.knowledge.*', icon: Library, permission: 'knowledge.view' },
    { label: 'AI platform', routeName: 'tenant.admin.ai.index', active: 'tenant.admin.ai.*', icon: Sparkles, permission: 'ai.view', feature: 'ai_platform' },
    { label: 'Administration', routeName: 'tenant.admin.administration.index', active: 'tenant.admin.administration.*', icon: ShieldCheck },
    { label: 'Connections', routeName: 'tenant.admin.connections.overview', active: 'tenant.admin.connections.*', icon: Cable, permission: 'connections.view' },
];

function Brand({ tenant }) {
    return (
        <Link href={route('tenant.admin.dashboard')} className="flex min-w-0 items-center gap-2.5">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-navy-900 text-white">
                {tenant?.logoUrl ? <img src={tenant.logoUrl} alt="" className="h-full w-full object-contain p-1" /> : <Avatar name={tenant?.companyName} size="sm" className="h-8 w-8 bg-transparent" />}
            </span>
            <span className="min-w-0">
                <span className="block truncate text-sm font-semibold text-navy-900">{tenant?.companyName || 'Your workspace'}</span>
                <span className="block truncate text-[11px] text-slate-400">PromptBot workspace</span>
            </span>
        </Link>
    );
}

function NavItem({ item, onNavigate }) {
    const Icon = item.icon;
    const isActive = route().current(item.active);

    return (
        <Link
            href={route(item.routeName)}
            onClick={onNavigate}
            aria-current={isActive ? 'page' : undefined}
            className={`flex h-9 items-center gap-2.5 rounded-md px-2.5 text-sm font-medium transition-colors ${
                isActive ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-100 hover:text-navy-900'
            }`}
        >
            <Icon className={`h-4 w-4 ${isActive ? 'text-brand-600' : 'text-slate-400'}`} strokeWidth={1.8} aria-hidden="true" />
            {item.label}
        </Link>
    );
}

function Sidebar({ tenant, user, onNavigate }) {
    const { auth, tenantFeatures } = usePage().props;
    const visibleNavigation = navigation.filter((item) => (!item.permission || auth?.permissions?.includes(item.permission)) && (!item.feature || tenantFeatures?.[item.feature]));

    return (
        <div className="flex h-full flex-col bg-white">
            <div className="flex h-header items-center border-b border-slate-200 px-4"><Brand tenant={tenant} /></div>
            <div className="flex-1 overflow-y-auto px-2 py-3">
                <p className="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Workspace</p>
                <nav className="space-y-0.5" aria-label="Tenant navigation">
                    {visibleNavigation.map((item) => <NavItem key={item.routeName} item={item} onNavigate={onNavigate} />)}
                </nav>
            </div>
            <div className="border-t border-slate-200 p-3">
                <div className="flex items-center gap-2.5 px-1">
                    <Avatar name={user?.name} size="sm" />
                    <span className="min-w-0">
                        <span className="block truncate text-xs font-medium text-slate-700">{user?.name || 'Team member'}</span>
                        <span className="block truncate text-[11px] text-slate-400">{user?.email}</span>
                    </span>
                </div>
            </div>
        </div>
    );
}

function UserMenu({ user }) {
    return (
        <DropdownMenu
            trigger={(
                <span className="flex items-center gap-2 px-1">
                    <Avatar name={user?.name} size="sm" />
                    <span className="hidden max-w-36 truncate text-sm font-medium text-slate-700 sm:block">{user?.name || 'Team member'}</span>
                </span>
            )}
            items={[
                { label: user?.email || 'Signed in', icon: User, disabled: true },
                { divider: true },
                { label: 'Log out', icon: LogOut, danger: true, onClick: () => router.post(route('tenant.logout')) },
            ]}
        />
    );
}

export default function AuthenticatedLayout({ header, title, children }) {
    const { auth, tenant, flash } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);

    if (auth?.guard !== 'tenant') {
        return <SuperadminLayout header={header}>{children}</SuperadminLayout>;
    }

    const user = auth.user;

    return (
        <div className="min-h-screen bg-[var(--color-bg)] text-slate-900">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-sidebar border-r border-slate-200 lg:block"><Sidebar tenant={tenant} user={user} /></aside>

            <div className={`fixed inset-0 z-50 lg:hidden ${mobileOpen ? 'pointer-events-auto' : 'pointer-events-none'}`} aria-hidden={!mobileOpen}>
                <button type="button" aria-label="Close navigation" onClick={() => setMobileOpen(false)} className={`absolute inset-0 bg-navy-950/50 transition-opacity ${mobileOpen ? 'opacity-100' : 'opacity-0'}`} />
                <aside className={`relative h-full w-sidebar border-r border-slate-200 bg-white shadow-soft-lg transition-transform duration-150 ${mobileOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                    <button type="button" aria-label="Close menu" onClick={() => setMobileOpen(false)} className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900"><X className="h-4 w-4" /></button>
                    <Sidebar tenant={tenant} user={user} onNavigate={() => setMobileOpen(false)} />
                </aside>
            </div>

            <div className="min-h-screen lg:pl-sidebar">
                <header className="sticky top-0 z-30 flex h-header items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <button type="button" aria-label="Open navigation" aria-expanded={mobileOpen} onClick={() => setMobileOpen(true)} className="flex h-9 w-9 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100 lg:hidden"><Menu className="h-4 w-4" /></button>
                        <p className="truncate text-sm font-semibold text-slate-800">{title || 'Workspace'}</p>
                    </div>
                    <UserMenu user={user} />
                </header>
                <main className="px-4 py-6 sm:px-6">
                    {flash?.status && <Alert tone="success" className="mb-4">{flash.status}</Alert>}
                    {flash?.error && <Alert tone="danger" className="mb-4">{flash.error}</Alert>}
                    {header && <div className="mb-6">{header}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}
