import ApplicationLogo from '@/Components/ApplicationLogo';
import Alert from '@/Components/UI/Alert';
import Avatar from '@/Components/UI/Avatar';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Activity, BadgeDollarSign, BarChart3, Building2, ChevronsLeft, ChevronsRight, CreditCard,
    Flag, Headphones, LayoutDashboard, LogOut, Menu, ReceiptText, Settings, User,
    SlidersHorizontal, Tags, X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const can = (auth, permission) => !permission || auth?.permissions?.includes(permission);
const matches = (current, patterns = []) => current && patterns.some((pattern) => pattern.endsWith('.*') ? current.startsWith(pattern.slice(0, -1)) : current === pattern || current.startsWith(`${pattern}.`));

const sections = [
    { title: 'Overview', items: [
        { label: 'Dashboard', route: 'superadmin.dashboard', patterns: ['superadmin.dashboard'], permission: 'dashboard.view', icon: LayoutDashboard },
    ]},
    { title: 'Tenants', items: [
        { label: 'Tenants & subdomains', route: 'superadmin.tenants.index', patterns: ['superadmin.tenants.*'], permission: 'tenants.view', icon: Building2 },
    ]},
    { title: 'Billing', items: [
        { label: 'Plans', route: 'superadmin.billing.plans.index', patterns: ['superadmin.plans.*', 'superadmin.billing.plans.*'], permission: 'plans.view', icon: Tags },
        { label: 'Subscriptions', route: 'superadmin.billing.subscriptions.index', patterns: ['superadmin.subscriptions.*', 'superadmin.billing.subscriptions.*'], permission: 'subscriptions.view', icon: BadgeDollarSign },
        { label: 'Payments', route: 'superadmin.billing.payments.index', patterns: ['superadmin.billing.payments.*'], permission: 'payments.view', icon: CreditCard },
        { label: 'Invoices', route: 'superadmin.billing.invoices.index', patterns: ['superadmin.billing.invoices.*'], permission: 'invoices.view', icon: ReceiptText },
    ]},
    { title: 'Support & operations', items: [
        { label: 'Tickets', route: 'superadmin.tickets.index', patterns: ['superadmin.tickets.*'], permission: 'support.view', icon: Headphones },
        { label: 'Reports', route: 'superadmin.reports.index', patterns: ['superadmin.reports.*'], permission: 'dashboard.view', icon: BarChart3 },
        { label: 'Website customization', route: 'superadmin.website.index', patterns: ['superadmin.website.*'], permission: 'website.view', icon: SlidersHorizontal },
        { label: 'System health', route: 'superadmin.operations.health', patterns: ['superadmin.operations.*'], permission: 'operations.view', icon: Activity },
    ]},
    { title: 'Configuration', items: [
        { label: 'Feature flags', route: 'superadmin.features.index', patterns: ['superadmin.features.*'], permission: 'features.view', icon: Flag },
        { label: 'General settings', route: 'superadmin.system.settings.index', patterns: ['superadmin.system.settings.*'], permission: 'settings.view', icon: Settings },
    ]},
];

function Brand({ platform, collapsed }) {
    return (
        <Link href={route('superadmin.dashboard')} className="flex min-w-0 items-center gap-2.5">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-navy-900 text-white">
                {platform?.logoUrl ? <img src={platform.logoUrl} alt="" className="h-full w-full object-contain p-1" /> : <ApplicationLogo className="h-5 w-5 fill-current" />}
            </span>
            {!collapsed && (
                <span className="min-w-0">
                    <span className="block truncate text-sm font-semibold text-navy-900">{platform?.name || 'PromptBot'}</span>
                    <span className="block truncate text-[11px] text-slate-400">Superadmin console</span>
                </span>
            )}
        </Link>
    );
}

function NavItem({ item, active, onNavigate, collapsed }) {
    const Icon = item.icon;

    return (
        <Link
            href={route(item.route)}
            onClick={onNavigate}
            aria-current={active ? 'page' : undefined}
            title={collapsed ? item.label : undefined}
            className={`flex h-9 items-center gap-2.5 rounded-md px-2.5 text-sm font-medium transition-colors ${collapsed ? 'justify-center' : ''} ${
                active ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-100 hover:text-navy-900'
            }`}
        >
            <Icon className={`h-4 w-4 shrink-0 ${active ? 'text-brand-600' : 'text-slate-400'}`} strokeWidth={1.8} aria-hidden="true" />
            {!collapsed && item.label}
        </Link>
    );
}

function Sidebar({ auth, platform, currentRoute, onNavigate, collapsed }) {
    return (
        <div className="flex h-full flex-col bg-white">
            <div className="flex h-header items-center border-b border-slate-200 px-4">
                <Brand platform={platform} collapsed={collapsed} />
            </div>
            <nav className="flex-1 overflow-y-auto px-2 py-3" aria-label="Superadmin navigation">
                {sections.map((section) => {
                    const items = section.items.filter((item) => can(auth, item.permission));
                    if (!items.length) return null;
                    return (
                        <div key={section.title} className="mb-4 last:mb-0">
                            {!collapsed && <p className="mb-1 px-2.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{section.title}</p>}
                            <div className="space-y-0.5">
                                {items.map((item) => (
                                    <NavItem key={item.label} item={item} active={matches(currentRoute, item.patterns)} collapsed={collapsed} onNavigate={onNavigate} />
                                ))}
                            </div>
                        </div>
                    );
                })}
            </nav>
        </div>
    );
}

function UserMenu({ user }) {
    return (
        <DropdownMenu
            trigger={(
                <span className="flex items-center gap-2 px-1">
                    <Avatar name={user?.name} size="sm" />
                    <span className="hidden max-w-36 truncate text-sm font-medium text-slate-700 sm:block">{user?.name || 'Administrator'}</span>
                </span>
            )}
            items={[
                { label: user?.email || 'Signed in', icon: User, disabled: true },
                { divider: true },
                { label: 'Log out', icon: LogOut, danger: true, onClick: () => router.post(route('logout')) },
            ]}
        />
    );
}

export default function SuperadminLayout({ header, breadcrumbs, children }) {
    const { auth, flash, platform } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(() => typeof window !== 'undefined' && window.localStorage.getItem('sa-sidebar-collapsed') === '1');
    const currentRoute = route().current();
    const currentItem = sections.flatMap((section) => section.items).find((item) => matches(currentRoute, item.patterns));

    useEffect(() => {
        window.localStorage.setItem('sa-sidebar-collapsed', collapsed ? '1' : '0');
    }, [collapsed]);

    return (
        <div className="min-h-screen bg-[var(--color-bg)] text-slate-900">
            <aside className={`fixed inset-y-0 left-0 z-40 hidden border-r border-slate-200 transition-[width] lg:block ${collapsed ? 'w-sidebar-collapsed' : 'w-sidebar'}`}>
                <Sidebar auth={auth} platform={platform} currentRoute={currentRoute} collapsed={collapsed} />
                <button
                    type="button"
                    onClick={() => setCollapsed((value) => !value)}
                    aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    className="absolute -right-3 top-16 flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-400 shadow-soft hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-800"
                >
                    {collapsed ? <ChevronsRight className="h-3.5 w-3.5" /> : <ChevronsLeft className="h-3.5 w-3.5" />}
                </button>
            </aside>

            <div className={`fixed inset-0 z-50 lg:hidden ${mobileOpen ? 'pointer-events-auto' : 'pointer-events-none'}`} aria-hidden={!mobileOpen}>
                <button type="button" aria-label="Close navigation" onClick={() => setMobileOpen(false)} className={`absolute inset-0 bg-navy-950/50 transition-opacity ${mobileOpen ? 'opacity-100' : 'opacity-0'}`} />
                <aside className={`relative h-full w-sidebar border-r border-slate-200 bg-white shadow-soft-lg transition-transform duration-150 ${mobileOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                    <button type="button" aria-label="Close menu" onClick={() => setMobileOpen(false)} className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900">
                        <X className="h-4 w-4" />
                    </button>
                    <Sidebar auth={auth} platform={platform} currentRoute={currentRoute} onNavigate={() => setMobileOpen(false)} />
                </aside>
            </div>

            <div className={`min-h-screen transition-[padding] ${collapsed ? 'lg:pl-sidebar-collapsed' : 'lg:pl-sidebar'}`}>
                <header className="sticky top-0 z-30 flex h-header items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <button type="button" aria-label="Open navigation" aria-expanded={mobileOpen} onClick={() => setMobileOpen(true)} className="flex h-9 w-9 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100 lg:hidden">
                            <Menu className="h-4 w-4" />
                        </button>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-slate-800">{currentItem?.label || 'Superadmin'}</p>
                            {breadcrumbs}
                        </div>
                    </div>
                    <UserMenu user={auth?.user} />
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
