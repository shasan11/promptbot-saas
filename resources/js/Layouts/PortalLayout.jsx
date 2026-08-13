import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bell, Building2, CreditCard, Headphones, LayoutDashboard, LogOut, Menu, Settings, Users, X } from 'lucide-react';
import { useState } from 'react';

const navigation = [
    { label: 'Overview', route: 'portal.dashboard', icon: LayoutDashboard },
    { label: 'Workspaces', route: 'portal.workspaces.index', icon: Building2 },
    { label: 'Billing', route: 'portal.billing.overview', icon: CreditCard, capability: 'billing' },
    { label: 'Members', route: 'portal.members.index', icon: Users, capability: 'members' },
    { label: 'Support', route: 'portal.support.index', icon: Headphones, capability: 'support', feature: 'support' },
    { label: 'Account', route: 'portal.profile.edit', icon: Settings },
];

export default function PortalLayout({ title, children, actions }) {
    const { auth, portal, flash, platform } = usePage().props;
    const [open, setOpen] = useState(false);
    const active = portal?.activeAccount;
    const membership = portal?.membership;
    const privileged = ['owner', 'admin'].includes(membership?.role);
    const capabilities = {
        billing: privileged || membership?.role === 'billing' || !!membership?.can_manage_billing,
        members: privileged || !!membership?.can_manage_members,
        support: privileged || !!membership?.can_manage_support,
    };

    const switchAccount = (event) => {
        const account = portal.accounts.find((item) => String(item.id) === event.target.value);
        if (account) router.post(route('portal.accounts.switch', account.public_uuid));
    };

    const sidebar = (
        <>
            <div className="flex h-16 items-center gap-3 border-b border-slate-800 px-5">
                <ApplicationLogo className="h-8 w-8 fill-current text-white" />
                <div><p className="font-bold text-white">{platform?.name || 'PromptBot'}</p><p className="text-xs text-slate-400">Customer Portal</p></div>
            </div>
            <nav className="space-y-1 p-3">
                {navigation.filter(item => (!item.capability || capabilities[item.capability]) && (!item.feature || portal?.features?.[item.feature] !== false)).map(({ label, route: routeName, icon: Icon }) => (
                    <Link key={routeName} href={route(routeName)} onClick={() => setOpen(false)} className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition ${route().current(`${routeName.split('.').slice(0, 2).join('.')}*`) || route().current(routeName) ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'}`}>
                        <Icon className="h-4 w-4" />{label}
                    </Link>
                ))}
            </nav>
        </>
    );

    return (
        <div className="min-h-screen bg-slate-50">
            <Head title={title} />
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 bg-slate-950 lg:block">{sidebar}</aside>
            {open && <div className="fixed inset-0 z-40 lg:hidden"><button className="absolute inset-0 bg-slate-950/50" onClick={() => setOpen(false)} /><aside className="relative h-full w-72 bg-slate-950">{sidebar}<button onClick={() => setOpen(false)} className="absolute right-3 top-3 text-slate-300"><X /></button></aside></div>}

            <div className="lg:pl-64">
                {platform?.maintenanceBanner && <div className="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-amber-950">{platform.maintenanceBanner}</div>}
                <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                    <button onClick={() => setOpen(true)} className="rounded-md p-2 text-slate-600 lg:hidden"><Menu className="h-5 w-5" /></button>
                    <select value={active?.id || ''} onChange={switchAccount} className="max-w-xs rounded-lg border-slate-300 py-2 text-sm font-semibold text-slate-800">
                        {(portal?.accounts || []).map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}
                    </select>
                    <div className="ml-auto flex items-center gap-3">
                        <Link href={route('portal.notifications.index')} className="relative rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50"><Bell className="h-4 w-4" />{portal?.unreadNotifications > 0 && <span className="absolute -right-1 -top-1 min-w-4 rounded-full bg-rose-600 px-1 text-center text-[10px] font-bold text-white">{portal.unreadNotifications}</span>}</Link>
                        <div className="hidden text-right sm:block"><p className="text-sm font-semibold text-slate-800">{auth?.user?.name}</p><p className="text-xs text-slate-500">{auth?.user?.email}</p></div>
                        <button onClick={() => router.post(route('portal.logout'))} title="Sign out" className="rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50"><LogOut className="h-4 w-4" /></button>
                    </div>
                </header>

                <main className="p-4 sm:p-6 lg:p-8">
                    <div className="mx-auto max-w-7xl">
                        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-medium text-indigo-600">{active?.name}</p><h1 className="text-2xl font-bold tracking-tight text-slate-950">{title}</h1></div>{actions}</div>
                        {flash?.status && <div className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.status}</div>}
                        {flash?.error && <div className="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{flash.error}</div>}
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
