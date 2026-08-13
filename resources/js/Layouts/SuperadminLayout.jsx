import ApplicationLogo from '@/Components/ApplicationLogo';
import Alert from '@/Components/UI/Alert';
import Avatar from '@/Components/UI/Avatar';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Activity, BadgeDollarSign, BarChart3, Building2, ChevronsLeft, ChevronsRight, CreditCard,
    Flag, Headphones, LayoutDashboard, LogOut, Menu, ReceiptText, Settings, User, Users,
    SlidersHorizontal, Tags, X, ShieldCheck, Search, Plus, TicketPercent, Mail, RotateCcw, TrendingUp, ServerCog,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const can = (auth, permission) => !permission || auth?.permissions?.includes(permission);
const matches = (current, patterns = []) => current && patterns.some((pattern) => pattern.endsWith('.*') ? current.startsWith(pattern.slice(0, -1)) : current === pattern || current.startsWith(`${pattern}.`));

const sections = [
    { title: 'Overview', items: [
        { label: 'Dashboard', route: 'superadmin.dashboard', patterns: ['superadmin.dashboard'], permission: 'dashboard.view', icon: LayoutDashboard },
    ]},
    { title: 'Customers', items: [
        { label: 'Accounts', route: 'superadmin.customers.accounts.index', patterns: ['superadmin.customers.accounts.*'], permission: 'customers.view', icon: Building2 },
        { label: 'Portal users', route: 'superadmin.customers.users.index', patterns: ['superadmin.customers.users.*'], permission: 'customers.view', icon: Users },
        { label: 'Services / Tenants', route: 'superadmin.services.index', patterns: ['superadmin.services.*', 'superadmin.tenants.*'], permission: 'tenants.view', icon: Building2 },
    ]},
    { title: 'Revenue', items: [
        { label: 'Overview', route: 'superadmin.revenue.index', patterns: ['superadmin.revenue.*'], permission: 'revenue.view', icon: BadgeDollarSign },
        { label: 'Growth', route: 'superadmin.growth.index', patterns: ['superadmin.growth.*'], permission: 'revenue.view', icon: TrendingUp },
        { label: 'Plans', route: 'superadmin.billing.plans.index', patterns: ['superadmin.plans.*', 'superadmin.billing.plans.*'], permission: 'plans.view', icon: Tags },
        { label: 'Subscriptions', route: 'superadmin.billing.subscriptions.index', patterns: ['superadmin.subscriptions.*', 'superadmin.billing.subscriptions.*'], permission: 'subscriptions.view', icon: BadgeDollarSign },
        { label: 'Payments', route: 'superadmin.billing.payments.index', patterns: ['superadmin.billing.payments.*'], permission: 'payments.view', icon: CreditCard },
        { label: 'Refunds', route: 'superadmin.billing.refunds.index', patterns: ['superadmin.billing.refunds.*'], permission: 'payments.view', icon: RotateCcw },
        { label: 'Invoices', route: 'superadmin.billing.invoices.index', patterns: ['superadmin.billing.invoices.*'], permission: 'invoices.view', icon: ReceiptText },
        { label: 'Coupons', route: 'superadmin.coupons.index', patterns: ['superadmin.coupons.*'], permission: 'coupons.view', icon: TicketPercent },
    ]},
    { title: 'Support', items: [
        { label: 'Tickets', route: 'superadmin.tickets.index', patterns: ['superadmin.tickets.*'], permission: 'support.view', icon: Headphones },
    ]},
    { title: 'Website & CMS', items: [
        { label: 'Website overview', route: 'superadmin.website.index', patterns: ['superadmin.website.*'], permission: 'website.view', icon: SlidersHorizontal },
    ]},
    { title: 'Operations', items: [
        { label: 'Provisioning', route: 'superadmin.operations.provisioning', patterns: ['superadmin.operations.provisioning'], permission: 'operations.view', icon: ServerCog },
        { label: 'Reports', route: 'superadmin.reports.index', patterns: ['superadmin.reports.*'], permission: 'dashboard.view', icon: BarChart3 },
        { label: 'Usage', route: 'superadmin.usage.index', patterns: ['superadmin.usage.*'], permission: 'usage.view', icon: BarChart3 },
        { label: 'System health', route: 'superadmin.operations.health', patterns: ['superadmin.operations.health'], permission: 'operations.view', icon: Activity },
    ]},
    { title: 'Security', items: [
        { label: 'Platform admins', route: 'superadmin.security.admins.index', patterns: ['superadmin.security.*'], permission: 'administrators.view', icon: ShieldCheck },
    ]},
    { title: 'Configuration', items: [
        { label: 'Feature flags', route: 'superadmin.features.index', patterns: ['superadmin.features.*'], permission: 'features.view', icon: Flag },
        { label: 'Email templates', route: 'superadmin.communications.email-templates.index', patterns: ['superadmin.communications.email-templates.*'], permission: 'communications.view', icon: Mail },
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
                { label: 'Two-factor security', icon: ShieldCheck, onClick: () => router.visit(route('superadmin.security.two-factor.setup')) },
                { divider: true },
                { label: 'Log out', icon: LogOut, danger: true, onClick: () => router.post(route('logout')) },
            ]}
        />
    );
}

function HeaderActions({ auth }) {
    const [query, setQuery] = useState('');
    const actions = [
        ['New account', 'superadmin.customers.accounts.create', 'customers.manage'],
        ['New service', 'superadmin.services.create', 'tenants.create'],
        ['New invoice', 'superadmin.billing.invoices.create', 'invoices.manage'],
        ['New ticket', 'superadmin.tickets.create', 'support.manage'],
        ['New plan', 'superadmin.plans.create', 'plans.create'],
        ['New page', 'superadmin.website.pages.create', 'website.manage'],
    ].filter(([, routeName, permission]) => typeof route === 'function' && route().has(routeName) && can(auth, permission));
    return <div className="flex items-center gap-2"><form onSubmit={event => { event.preventDefault(); if (query.trim()) router.get(route('superadmin.search'), { q: query.trim() }); }} className="hidden items-center md:flex"><Search className="relative left-7 h-4 w-4 text-slate-400" /><input data-platform-search value={query} onChange={event => setQuery(event.target.value)} className="w-64 rounded-lg border-slate-300 py-1.5 pl-9 text-sm" placeholder="Search platform…" /></form>{actions.length > 0 && <DropdownMenu trigger={<span className="flex h-9 items-center gap-1 rounded-lg bg-slate-900 px-3 text-sm font-semibold text-white"><Plus className="h-4 w-4" /> <span className="hidden sm:inline">Create</span></span>} items={actions.map(([label, routeName]) => ({ label, onClick: () => router.visit(route(routeName)) }))} />}<UserMenu user={auth?.user} /></div>;
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

    useEffect(() => {
        const shortcut = (event) => {
            if ((event.key === '/' && !event.ctrlKey && !event.metaKey) || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k')) {
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
                event.preventDefault();
                document.querySelector('[data-platform-search]')?.focus();
            }
        };
        window.addEventListener('keydown', shortcut);
        return () => window.removeEventListener('keydown', shortcut);
    }, []);

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
                    <HeaderActions auth={auth} />
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
