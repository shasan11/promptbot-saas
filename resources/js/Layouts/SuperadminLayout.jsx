import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity, BadgeDollarSign, Bot, Building2, ChevronDown, CreditCard, FileClock,
    FileText, Gauge, Gift, Headphones, LayoutDashboard, LogOut, Menu,
    MessageSquareText, Plug, ReceiptText, Settings, ShieldCheck,
    SlidersHorizontal, Sparkles, Tags, UserCog, UsersRound, X,
} from 'lucide-react';
import { useState } from 'react';

const can = (auth, permission) => auth?.permissions?.includes('*') || auth?.can?.[permission];
const initials = (name = '') => name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || 'SA';
const matches = (current, patterns = []) => current && patterns.some((pattern) => pattern.endsWith('.*') ? current.startsWith(pattern.slice(0, -1)) : current === pattern || current.startsWith(`${pattern}.`));

const sections = [
    { title: 'Overview', items: [
        { label: 'Dashboard', route: 'superadmin.dashboard', patterns: ['superadmin.dashboard'], permission: 'viewDashboard', icon: LayoutDashboard },
    ]},
    { title: 'Tenants', items: [
        { label: 'All tenants', route: 'superadmin.tenants.index', patterns: ['superadmin.tenants.*'], permission: 'viewTenants', icon: Building2 },
        { label: 'Tenant health', permission: 'viewTenants', icon: Activity, disabled: true },
    ]},
    { title: 'Billing', items: [
        { label: 'Plans', route: 'superadmin.billing.plans.index', patterns: ['superadmin.plans.*', 'superadmin.billing.plans.*'], permission: 'viewPlans', icon: Tags },
        { label: 'Subscriptions', route: 'superadmin.billing.subscriptions.index', patterns: ['superadmin.subscriptions.*', 'superadmin.billing.subscriptions.*'], permission: 'viewSubscriptions', icon: BadgeDollarSign },
        { label: 'Payments', route: 'superadmin.billing.payments.index', patterns: ['superadmin.billing.payments.*'], permission: 'viewPayments', icon: CreditCard },
        { label: 'Invoices', route: 'superadmin.billing.invoices.index', patterns: ['superadmin.billing.invoices.*'], permission: 'viewPayments', icon: ReceiptText },
        { label: 'Coupons', route: 'superadmin.billing.coupons.index', patterns: ['superadmin.billing.coupons.*'], permission: 'viewPayments', icon: Gift },
        { label: 'Gateways', route: 'superadmin.billing.gateways.index', patterns: ['superadmin.billing.gateways.*'], permission: 'viewPayments', icon: Plug },
    ]},
    { title: 'Platform', items: [
        { label: 'Features', route: 'superadmin.features.index', patterns: ['superadmin.features.*'], permission: 'viewFeatures', icon: Sparkles },
        { label: 'Feature flags', permission: 'viewFeatures', icon: SlidersHorizontal, disabled: true },
        { label: 'Usage metering', route: 'superadmin.platform.usage.index', patterns: ['superadmin.platform.usage.*'], permission: 'viewFeatures', icon: Gauge },
        { label: 'Integrations & AI', route: 'superadmin.platform.integrations.index', patterns: ['superadmin.platform.integrations.*'], permission: 'viewIntegrations', icon: Bot },
    ]},
    { title: 'Website', items: [
        { label: 'Customization', route: 'superadmin.website.index', patterns: ['superadmin.website.*'], permission: 'viewWebsite', icon: SlidersHorizontal },
    ]},
    { title: 'Customers', items: [
        { label: 'Communications', route: 'superadmin.communications.index', patterns: ['superadmin.communications.*'], permission: 'viewCommunications', icon: MessageSquareText },
        { label: 'Support', route: 'superadmin.support.index', patterns: ['superadmin.support.*'], permission: 'viewSupport', icon: Headphones },
    ]},
    { title: 'Operations', items: [
        { label: 'Health & queues', route: 'superadmin.operations.health', patterns: ['superadmin.operations.*'], permission: 'viewOperations', icon: Activity },
    ]},
    { title: 'System', items: [
        { label: 'Administrators', route: 'superadmin.system.administrators.index', patterns: ['superadmin.system.administrators.*'], permission: 'viewSettings', icon: UserCog },
        { label: 'Roles', route: 'superadmin.system.roles.index', patterns: ['superadmin.system.roles.*'], permission: 'viewSettings', icon: UsersRound },
        { label: 'Settings', route: 'superadmin.system.settings.index', patterns: ['superadmin.system.settings.*'], permission: 'viewSettings', icon: Settings },
        { label: 'Security', route: 'superadmin.system.security.index', patterns: ['superadmin.system.security.*'], permission: 'viewSettings', icon: ShieldCheck },
        { label: 'Audit logs', route: 'superadmin.system.audit-logs.index', patterns: ['superadmin.system.audit-logs.*'], permission: 'viewAuditLogs', icon: FileText },
        { label: 'Login attempts', route: 'superadmin.system.login-attempts.index', patterns: ['superadmin.system.login-attempts.*'], permission: 'viewAuditLogs', icon: FileClock },
    ]},
];

function Brand() {
    return (
        <Link href={route('superadmin.dashboard')} className="flex min-w-0 items-center gap-2.5">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-950 text-white"><ApplicationLogo className="h-5 w-5 fill-current" /></span>
            <span className="min-w-0">
                <span className="block truncate text-sm font-semibold text-slate-950">PromptBot</span>
                <span className="block truncate text-[11px] text-slate-500">Superadmin</span>
            </span>
        </Link>
    );
}

function NavItem({ item, active, onNavigate }) {
    const Icon = item.icon;
    if (item.disabled) return <span className="flex h-8 cursor-not-allowed items-center gap-2.5 rounded-md px-2.5 text-sm text-slate-300"><Icon className="h-4 w-4" strokeWidth={1.8} />{item.label}</span>;

    return (
        <Link href={route(item.route)} onClick={onNavigate} aria-current={active ? 'page' : undefined} className={`flex h-8 items-center gap-2.5 rounded-md px-2.5 text-sm font-medium transition-colors ${active ? 'bg-slate-100 text-slate-950' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'}`}>
            <Icon className={`h-4 w-4 ${active ? 'text-indigo-600' : 'text-slate-400'}`} strokeWidth={1.8} />{item.label}
        </Link>
    );
}

function Sidebar({ auth, currentRoute, onNavigate }) {
    return (
        <div className="flex h-full flex-col bg-white">
            <div className="flex h-14 items-center border-b border-slate-200 px-4"><Brand /></div>
            <nav className="flex-1 overflow-y-auto px-2 py-3" aria-label="Superadmin navigation">
                {sections.map((section) => {
                    const items = section.items.filter((item) => can(auth, item.permission));
                    if (!items.length) return null;
                    return (
                        <div key={section.title} className="mb-4 last:mb-0">
                            <p className="mb-1 px-2.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{section.title}</p>
                            <div className="space-y-0.5">{items.map((item) => <NavItem key={item.label} item={item} active={matches(currentRoute, item.patterns)} onNavigate={onNavigate} />)}</div>
                        </div>
                    );
                })}
            </nav>
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
                <Dropdown.Link href={route('logout')} method="post" as="button" className="flex items-center gap-2 px-3 py-2 text-slate-700"><LogOut className="h-4 w-4 text-slate-400" /> Log out</Dropdown.Link>
            </Dropdown.Content>
        </Dropdown>
    );
}

export default function SuperadminLayout({ header, children }) {
    const { auth, flash } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const currentRoute = route().current();
    const currentItem = sections.flatMap((section) => section.items).find((item) => matches(currentRoute, item.patterns));

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-slate-200 lg:block"><Sidebar auth={auth} currentRoute={currentRoute} /></aside>

            <div className={`fixed inset-0 z-50 lg:hidden ${mobileOpen ? 'pointer-events-auto' : 'pointer-events-none'}`} aria-hidden={!mobileOpen}>
                <button type="button" aria-label="Close navigation" onClick={() => setMobileOpen(false)} className={`absolute inset-0 bg-slate-950/30 transition-opacity ${mobileOpen ? 'opacity-100' : 'opacity-0'}`} />
                <aside className={`relative h-full w-64 border-r border-slate-200 bg-white shadow-xl transition-transform duration-200 ${mobileOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                    <button type="button" aria-label="Close menu" onClick={() => setMobileOpen(false)} className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900"><X className="h-4 w-4" /></button>
                    <Sidebar auth={auth} currentRoute={currentRoute} onNavigate={() => setMobileOpen(false)} />
                </aside>
            </div>

            <div className="min-h-screen lg:pl-64">
                <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <button type="button" aria-label="Open navigation" aria-expanded={mobileOpen} onClick={() => setMobileOpen(true)} className="flex h-8 w-8 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100 lg:hidden"><Menu className="h-4 w-4" /></button>
                        <p className="truncate text-sm font-semibold text-slate-800">{currentItem?.label || 'Superadmin'}</p>
                    </div>
                    <UserMenu user={auth?.user} />
                </header>
                <main className="px-4 py-5 sm:px-6">
                    {flash?.status && <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.status}</div>}
                    {flash?.error && <div className="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{flash.error}</div>}
                    {header && <div className="mb-5">{header}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}
