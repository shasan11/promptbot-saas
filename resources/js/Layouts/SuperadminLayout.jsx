import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity, BadgeDollarSign, BarChart3, Building2, ChevronDown, CreditCard,
    Headphones, LayoutDashboard, LogOut, Menu, ReceiptText, Settings,
    SlidersHorizontal, Tags, X,
} from 'lucide-react';
import { useState } from 'react';

const can = (auth, permission) => !permission || auth?.permissions?.includes(permission);
const initials = (name = '') => name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || 'SA';
const matches = (current, patterns = []) => current && patterns.some((pattern) => pattern.endsWith('.*') ? current.startsWith(pattern.slice(0, -1)) : current === pattern || current.startsWith(`${pattern}.`));

const sections = [
    { title: 'Overview', items: [
        { label: 'Dashboard', route: 'superadmin.dashboard', patterns: ['superadmin.dashboard'], permission: 'dashboard.view', icon: LayoutDashboard },
    ]},
    { title: 'Tenant Management', items: [
        { label: 'Tenants & subdomains', route: 'superadmin.tenants.index', patterns: ['superadmin.tenants.*'], permission: 'tenants.view', icon: Building2 },
    ]},
    { title: 'Billing', items: [
        { label: 'Plans', route: 'superadmin.billing.plans.index', patterns: ['superadmin.plans.*', 'superadmin.billing.plans.*'], permission: 'plans.view', icon: Tags },
        { label: 'Subscriptions', route: 'superadmin.billing.subscriptions.index', patterns: ['superadmin.subscriptions.*', 'superadmin.billing.subscriptions.*'], permission: 'subscriptions.view', icon: BadgeDollarSign },
        { label: 'Payments', route: 'superadmin.billing.payments.index', patterns: ['superadmin.billing.payments.*'], permission: 'payments.view', icon: CreditCard },
        { label: 'Invoices', route: 'superadmin.billing.invoices.index', patterns: ['superadmin.billing.invoices.*'], permission: 'invoices.view', icon: ReceiptText },
    ]},
    { title: 'Operations', items: [
        { label: 'Tickets', route: 'superadmin.tickets.index', patterns: ['superadmin.tickets.*'], permission: 'support.view', icon: Headphones },
        { label: 'Reports', route: 'superadmin.reports.index', patterns: ['superadmin.reports.*'], permission: 'dashboard.view', icon: BarChart3 },
        { label: 'Website customization', route: 'superadmin.website.index', patterns: ['superadmin.website.*'], permission: 'website.view', icon: SlidersHorizontal },
        { label: 'System health', route: 'superadmin.operations.health', patterns: ['superadmin.operations.*'], permission: 'operations.view', icon: Activity },
    ]},
    { title: 'Configuration', items: [
        { label: 'General settings', route: 'superadmin.system.settings.index', patterns: ['superadmin.system.settings.*'], permission: 'settings.view', icon: Settings },
    ]},
];

function Brand({ platform }) {
    const primaryColor = platform?.primaryColor || '#0F172A';

    return (
        <Link href={route('superadmin.dashboard')} className="flex min-w-0 items-center gap-2.5">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-md text-white" style={{ backgroundColor: primaryColor }}>
                {platform?.logoUrl ? <img src={platform.logoUrl} alt="" className="h-full w-full object-contain p-1" /> : <ApplicationLogo className="h-5 w-5 fill-current" />}
            </span>
            <span className="min-w-0">
                <span className="block truncate text-sm font-semibold text-slate-950">{platform?.name || 'PromptBot'}</span>
                <span className="block truncate text-[11px] text-slate-500">Superadmin</span>
            </span>
        </Link>
    );
}

function NavItem({ item, active, onNavigate, accentColor }) {
    const Icon = item.icon;

    return (
        <Link href={route(item.route)} onClick={onNavigate} aria-current={active ? 'page' : undefined} className={`flex h-8 items-center gap-2.5 rounded-md px-2.5 text-sm font-medium transition-colors ${active ? 'bg-slate-100 text-slate-950' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'}`}>
            <Icon className="h-4 w-4" style={{ color: active ? accentColor : undefined }} strokeWidth={1.8} />{item.label}
        </Link>
    );
}

function Sidebar({ auth, platform, currentRoute, onNavigate }) {
    const accentColor = platform?.secondaryColor || '#4F46E5';

    return (
        <div className="flex h-full flex-col bg-white">
            <div className="flex h-14 items-center border-b border-slate-200 px-4"><Brand platform={platform} /></div>
            <nav className="flex-1 overflow-y-auto px-2 py-3" aria-label="Superadmin navigation">
                {sections.map((section) => {
                    const items = section.items.filter((item) => can(auth, item.permission));
                    if (!items.length) return null;
                    return (
                        <div key={section.title} className="mb-4 last:mb-0">
                            <p className="mb-1 px-2.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{section.title}</p>
                            <div className="space-y-0.5">{items.map((item) => <NavItem key={item.label} item={item} active={matches(currentRoute, item.patterns)} accentColor={accentColor} onNavigate={onNavigate} />)}</div>
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
    const { auth, flash, platform } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const currentRoute = route().current();
    const currentItem = sections.flatMap((section) => section.items).find((item) => matches(currentRoute, item.patterns));

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-slate-200 lg:block"><Sidebar auth={auth} platform={platform} currentRoute={currentRoute} /></aside>

            <div className={`fixed inset-0 z-50 lg:hidden ${mobileOpen ? 'pointer-events-auto' : 'pointer-events-none'}`} aria-hidden={!mobileOpen}>
                <button type="button" aria-label="Close navigation" onClick={() => setMobileOpen(false)} className={`absolute inset-0 bg-slate-950/30 transition-opacity ${mobileOpen ? 'opacity-100' : 'opacity-0'}`} />
                <aside className={`relative h-full w-64 border-r border-slate-200 bg-white shadow-xl transition-transform duration-200 ${mobileOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                    <button type="button" aria-label="Close menu" onClick={() => setMobileOpen(false)} className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900"><X className="h-4 w-4" /></button>
                    <Sidebar auth={auth} platform={platform} currentRoute={currentRoute} onNavigate={() => setMobileOpen(false)} />
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
