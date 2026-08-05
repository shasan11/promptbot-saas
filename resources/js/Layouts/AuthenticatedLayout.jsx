import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import SuperadminLayout from '@/Layouts/SuperadminLayout';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, LayoutDashboard, LogOut, Menu, Settings, Users, X } from 'lucide-react';
import { useState } from 'react';

const navigation = [
    { label: 'Dashboard', routeName: 'tenant.admin.dashboard', active: 'tenant.admin.dashboard', icon: LayoutDashboard },
    { label: 'Users', routeName: 'tenant.admin.users.index', active: 'tenant.admin.users.*', icon: Users },
    { label: 'Settings', routeName: 'tenant.admin.settings.edit', active: 'tenant.admin.settings.*', icon: Settings },
];

const initials = (name = '') =>
    name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || 'TA';

function Brand() {
    return (
        <Link href={route('tenant.admin.dashboard')} className="flex min-w-0 items-center gap-2.5">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-950 text-white">
                <ApplicationLogo className="h-5 w-5 fill-current" />
            </span>
            <span className="min-w-0">
                <span className="block truncate text-sm font-semibold text-slate-950">PromptBot</span>
                <span className="block truncate text-[11px] text-slate-500">Tenant admin</span>
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
                isActive
                    ? 'bg-slate-100 text-slate-950'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'
            }`}
        >
            <Icon className={`h-4 w-4 ${isActive ? 'text-indigo-600' : 'text-slate-400'}`} strokeWidth={1.8} />
            {item.label}
        </Link>
    );
}

function Sidebar({ user, onNavigate }) {
    return (
        <div className="flex h-full flex-col bg-white">
            <div className="flex h-14 items-center border-b border-slate-200 px-4"><Brand /></div>
            <div className="flex-1 overflow-y-auto px-2 py-3">
                <p className="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Workspace</p>
                <nav className="space-y-0.5" aria-label="Tenant navigation">
                    {navigation.map((item) => <NavItem key={item.routeName} item={item} onNavigate={onNavigate} />)}
                </nav>
            </div>
            <div className="border-t border-slate-200 p-3">
                <div className="flex items-center gap-2.5 px-1">
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-[11px] font-semibold text-slate-700">{initials(user?.name)}</span>
                    <span className="min-w-0">
                        <span className="block truncate text-xs font-medium text-slate-700">{user?.name || 'Administrator'}</span>
                        <span className="block truncate text-[11px] text-slate-400">{user?.email}</span>
                    </span>
                </div>
            </div>
        </div>
    );
}

function UserMenu({ user }) {
    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button type="button" className="flex h-9 items-center gap-2 rounded-md px-1.5 text-left transition-colors hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    <span className="flex h-7 w-7 items-center justify-center rounded-md bg-slate-200 text-[11px] font-semibold text-slate-700">{initials(user?.name)}</span>
                    <span className="hidden max-w-36 truncate text-sm font-medium text-slate-700 sm:block">{user?.name || 'Administrator'}</span>
                    <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
                </button>
            </Dropdown.Trigger>
            <Dropdown.Content align="right" width="48" contentClasses="overflow-hidden bg-white py-1">
                <div className="border-b border-slate-100 px-3 py-2.5">
                    <p className="truncate text-sm font-medium text-slate-800">{user?.name || 'Administrator'}</p>
                    <p className="truncate text-xs text-slate-500">{user?.email}</p>
                </div>
                <Dropdown.Link href={route('tenant.logout')} method="post" as="button" className="flex items-center gap-2 px-3 py-2 text-slate-700">
                    <LogOut className="h-4 w-4 text-slate-400" /> Log out
                </Dropdown.Link>
            </Dropdown.Content>
        </Dropdown>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const { user, guard } = usePage().props.auth;
    const [mobileOpen, setMobileOpen] = useState(false);

    if (guard !== 'tenant') return <SuperadminLayout header={header}>{children}</SuperadminLayout>;

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-60 border-r border-slate-200 lg:block"><Sidebar user={user} /></aside>

            <div className={`fixed inset-0 z-50 lg:hidden ${mobileOpen ? 'pointer-events-auto' : 'pointer-events-none'}`} aria-hidden={!mobileOpen}>
                <button type="button" aria-label="Close navigation" onClick={() => setMobileOpen(false)} className={`absolute inset-0 bg-slate-950/30 transition-opacity ${mobileOpen ? 'opacity-100' : 'opacity-0'}`} />
                <aside className={`relative h-full w-60 border-r border-slate-200 bg-white shadow-xl transition-transform duration-200 ${mobileOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                    <button type="button" aria-label="Close menu" onClick={() => setMobileOpen(false)} className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900"><X className="h-4 w-4" /></button>
                    <Sidebar user={user} onNavigate={() => setMobileOpen(false)} />
                </aside>
            </div>

            <div className="min-h-screen lg:pl-60">
                <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <button type="button" aria-label="Open navigation" aria-expanded={mobileOpen} onClick={() => setMobileOpen(true)} className="flex h-8 w-8 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100 lg:hidden"><Menu className="h-4 w-4" /></button>
                        <p className="truncate text-sm font-semibold text-slate-800">Tenant administration</p>
                    </div>
                    <UserMenu user={user} />
                </header>
                <main className="px-4 py-5 sm:px-6">
                    {header && <div className="mb-5">{header}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}
