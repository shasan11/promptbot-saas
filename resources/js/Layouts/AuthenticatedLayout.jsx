import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import SuperadminLayout from '@/Layouts/SuperadminLayout';
import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ChevronDown,
    LayoutDashboard,
    LogOut,
    Menu,
    Settings,
    UserCircle,
    Users,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';

const navigation = [
    {
        label: 'Dashboard',
        description: 'Overview and insights',
        routeName: 'tenant.admin.dashboard',
        activePattern: 'tenant.admin.dashboard',
        icon: LayoutDashboard,
    },
    {
        label: 'Users',
        description: 'Manage team access',
        routeName: 'tenant.admin.users.index',
        activePattern: 'tenant.admin.users.*',
        icon: Users,
    },
    {
        label: 'Settings',
        description: 'Workspace preferences',
        routeName: 'tenant.admin.settings.edit',
        activePattern: 'tenant.admin.settings.*',
        icon: Settings,
    },
];

function getInitials(name = '') {
    const initials = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

    return initials || 'TA';
}

function NavigationItem({ item, mobile = false, onNavigate }) {
    const Icon = item.icon;
    const active = route().current(item.activePattern);

    return (
        <Link
            href={route(item.routeName)}
            onClick={onNavigate}
            aria-current={active ? 'page' : undefined}
            className={[
                'group relative flex items-center gap-3 rounded-xl px-3 py-3 transition-all duration-200',
                active
                    ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950',
                mobile ? 'w-full' : '',
            ].join(' ')}
        >
            {active && (
                <span className="absolute inset-y-3 left-0 w-1 rounded-r-full bg-indigo-600" />
            )}

            <span
                className={[
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-colors',
                    active
                        ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200/70'
                        : 'bg-slate-100 text-slate-500 group-hover:bg-white group-hover:text-slate-800 group-hover:shadow-sm group-hover:ring-1 group-hover:ring-slate-200',
                ].join(' ')}
            >
                <Icon className="h-5 w-5" strokeWidth={1.9} />
            </span>

            <span className="min-w-0">
                <span className="block text-sm font-semibold">{item.label}</span>
                <span
                    className={[
                        'mt-0.5 block truncate text-xs',
                        active ? 'text-indigo-500' : 'text-slate-400',
                    ].join(' ')}
                >
                    {item.description}
                </span>
            </span>
        </Link>
    );
}

function Brand() {
    return (
        <Link
            href={route('tenant.admin.dashboard')}
            className="group flex min-w-0 items-center gap-3"
        >
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 shadow-lg shadow-indigo-600/20 transition-transform duration-200 group-hover:scale-[1.03]">
                <ApplicationLogo className="h-7 w-7 fill-current text-white" />
            </span>

            <span className="min-w-0">
                <span className="block truncate text-[11px] font-bold uppercase tracking-[0.18em] text-indigo-600">
                    Workspace
                </span>
                <span className="mt-0.5 block truncate text-base font-bold tracking-tight text-slate-950">
                    Tenant Admin
                </span>
            </span>
        </Link>
    );
}

function UserMenu({ user, logoutRoute }) {
    const initials = useMemo(() => getInitials(user?.name), [user?.name]);

    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button
                    type="button"
                    className="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-2 text-left shadow-sm transition hover:border-slate-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                >
                    <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-100 to-violet-100 text-sm font-bold text-indigo-700">
                        {initials}
                    </span>

                    <span className="hidden max-w-40 min-w-0 sm:block">
                        <span className="block truncate text-sm font-semibold text-slate-800">
                            {user?.name || 'Tenant administrator'}
                        </span>
                        <span className="block truncate text-xs text-slate-400">
                            {user?.email || 'Administrator'}
                        </span>
                    </span>

                    <ChevronDown className="h-4 w-4 text-slate-400 transition-transform group-data-[state=open]:rotate-180" />
                </button>
            </Dropdown.Trigger>

            <Dropdown.Content align="right" width="48">
                <div className="border-b border-slate-100 px-4 py-3">
                    <p className="truncate text-sm font-semibold text-slate-800">
                        {user?.name || 'Tenant administrator'}
                    </p>
                    <p className="mt-0.5 truncate text-xs text-slate-400">
                        {user?.email}
                    </p>
                </div>

                <Dropdown.Link
                    href={route(logoutRoute)}
                    method="post"
                    as="button"
                    className="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 transition hover:bg-rose-50"
                >
                    <LogOut className="h-4 w-4" />
                    Log out
                </Dropdown.Link>
            </Dropdown.Content>
        </Dropdown>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const { user, guard } = usePage().props.auth;
    const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);
    const isTenant = guard === 'tenant';

    if (!isTenant) {
        return <SuperadminLayout header={header}>{children}</SuperadminLayout>;
    }

    const logoutRoute = 'tenant.logout';

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-slate-200/80 bg-white lg:flex lg:flex-col">
                <div className="flex h-20 items-center border-b border-slate-100 px-6">
                    <Brand />
                </div>

                <div className="flex flex-1 flex-col overflow-y-auto px-4 py-6">
                    <div className="mb-3 px-3 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">
                        Main menu
                    </div>

                    <nav className="space-y-1.5" aria-label="Tenant navigation">
                        {navigation.map((item) => (
                            <NavigationItem key={item.routeName} item={item} />
                        ))}
                    </nav>

                    <div className="mt-auto pt-8">
                        <div className="overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-violet-50 p-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm ring-1 ring-indigo-100">
                                <Building2 className="h-5 w-5" />
                            </div>
                            <p className="mt-3 text-sm font-bold text-slate-800">
                                Tenant workspace
                            </p>
                            <p className="mt-1 text-xs leading-5 text-slate-500">
                                Manage users and workspace preferences from one secure place.
                            </p>
                            <div className="mt-3 flex items-center gap-2 text-xs font-semibold text-indigo-700">
                                <span className="h-2 w-2 rounded-full bg-indigo-500 ring-4 ring-indigo-100" />
                                Admin access
                            </div>
                        </div>
                    </div>
                </div>

                <div className="border-t border-slate-100 p-4">
                    <div className="flex items-center gap-3 rounded-2xl bg-slate-50 p-3">
                        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200">
                            <UserCircle className="h-5 w-5" />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-semibold text-slate-800">
                                {user?.name || 'Administrator'}
                            </span>
                            <span className="block truncate text-xs text-slate-400">
                                Tenant administrator
                            </span>
                        </span>
                    </div>
                </div>
            </aside>

            {/* Mobile navigation overlay */}
            <div
                className={[
                    'fixed inset-0 z-50 lg:hidden',
                    mobileNavigationOpen ? 'pointer-events-auto' : 'pointer-events-none',
                ].join(' ')}
                aria-hidden={!mobileNavigationOpen}
            >
                <button
                    type="button"
                    aria-label="Close navigation"
                    onClick={() => setMobileNavigationOpen(false)}
                    className={[
                        'absolute inset-0 bg-slate-950/40 backdrop-blur-sm transition-opacity duration-300',
                        mobileNavigationOpen ? 'opacity-100' : 'opacity-0',
                    ].join(' ')}
                />

                <aside
                    className={[
                        'relative flex h-full w-[88%] max-w-sm flex-col bg-white shadow-2xl transition-transform duration-300 ease-out',
                        mobileNavigationOpen ? 'translate-x-0' : '-translate-x-full',
                    ].join(' ')}
                >
                    <div className="flex h-20 items-center justify-between border-b border-slate-100 px-5">
                        <Brand />
                        <button
                            type="button"
                            onClick={() => setMobileNavigationOpen(false)}
                            className="flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            aria-label="Close menu"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    <div className="flex-1 overflow-y-auto px-4 py-6">
                        <div className="mb-3 px-3 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">
                            Main menu
                        </div>
                        <nav className="space-y-1.5" aria-label="Mobile tenant navigation">
                            {navigation.map((item) => (
                                <NavigationItem
                                    key={item.routeName}
                                    item={item}
                                    mobile
                                    onNavigate={() => setMobileNavigationOpen(false)}
                                />
                            ))}
                        </nav>
                    </div>

                    <div className="border-t border-slate-100 p-4">
                        <Link
                            href={route(logoutRoute)}
                            method="post"
                            as="button"
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2"
                        >
                            <LogOut className="h-4 w-4" />
                            Log out
                        </Link>
                    </div>
                </aside>
            </div>

            {/* Main application area */}
            <div className="min-h-screen lg:pl-72">
                <header className="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-screen-2xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <div className="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setMobileNavigationOpen(true)}
                                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-950 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 lg:hidden"
                                aria-label="Open navigation"
                                aria-expanded={mobileNavigationOpen}
                            >
                                <Menu className="h-5 w-5" />
                            </button>

                            <div className="min-w-0">
                                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-600">
                                    Administration
                                </p>
                                <p className="mt-0.5 truncate text-sm text-slate-500">
                                    Manage your tenant workspace
                                </p>
                            </div>
                        </div>

                        <UserMenu user={user} logoutRoute={logoutRoute} />
                    </div>
                </header>

                {header && (
                    <section className="border-b border-slate-200/80 bg-white">
                        <div className="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                            {header}
                        </div>
                    </section>
                )}

                <main className="mx-auto w-full max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
