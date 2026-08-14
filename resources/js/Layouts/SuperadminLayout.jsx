import Alert from '@/Components/UI/Alert';
import Avatar from '@/Components/UI/Avatar';
import DropdownMenu from '@/Components/UI/DropdownMenu';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    BadgeDollarSign,
    BarChart3,
    Bot,
    Building2,
    ChevronsLeft,
    ChevronsRight,
    Cpu,
    CreditCard,
    Flag,
    Headphones,
    LayoutDashboard,
    LogOut,
    Mail,
    Menu,
    ReceiptText,
    RotateCcw,
    ScrollText,
    Search,
    ServerCog,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    Sparkles,
    Tags,
    TicketPercent,
    ToggleLeft,
    TrendingUp,
    User,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const can = (auth, permission) => !permission || auth?.permissions?.includes(permission);

const matches = (current, patterns = []) => current && patterns.some((pattern) => (
    pattern.endsWith('.*')
        ? current.startsWith(pattern.slice(0, -1))
        : current === pattern || current.startsWith(`${pattern}.`)
));

const sections = [
    {
        title: 'Overview',
        items: [
            { label: 'Dashboard', route: 'superadmin.dashboard', patterns: ['superadmin.dashboard'], permission: 'dashboard.view', icon: LayoutDashboard },
        ],
    },
    {
        title: 'Customers',
        items: [
            { label: 'Accounts', route: 'superadmin.customers.accounts.index', patterns: ['superadmin.customers.accounts.*'], permission: 'customers.view', icon: Building2 },
            { label: 'Portal users', route: 'superadmin.customers.users.index', patterns: ['superadmin.customers.users.*'], permission: 'customers.view', icon: Users },
            { label: 'Services / Tenants', route: 'superadmin.services.index', patterns: ['superadmin.services.*', 'superadmin.tenants.*'], permission: 'tenants.view', icon: Building2 },
        ],
    },
    {
        title: 'Revenue',
        items: [
            { label: 'Overview', route: 'superadmin.revenue.index', patterns: ['superadmin.revenue.*'], permission: 'revenue.view', icon: BadgeDollarSign },
            { label: 'Growth', route: 'superadmin.growth.index', patterns: ['superadmin.growth.*'], permission: 'revenue.view', icon: TrendingUp },
            { label: 'Plans', route: 'superadmin.billing.plans.index', patterns: ['superadmin.plans.*', 'superadmin.billing.plans.*'], permission: 'plans.view', icon: Tags },
            { label: 'Subscriptions', route: 'superadmin.billing.subscriptions.index', patterns: ['superadmin.subscriptions.*', 'superadmin.billing.subscriptions.*'], permission: 'subscriptions.view', icon: BadgeDollarSign },
            { label: 'Payments', route: 'superadmin.billing.payments.index', patterns: ['superadmin.billing.payments.*'], permission: 'payments.view', icon: CreditCard },
            { label: 'Refunds', route: 'superadmin.billing.refunds.index', patterns: ['superadmin.billing.refunds.*'], permission: 'payments.view', icon: RotateCcw },
            { label: 'Invoices', route: 'superadmin.billing.invoices.index', patterns: ['superadmin.billing.invoices.*'], permission: 'invoices.view', icon: ReceiptText },
            { label: 'Coupons', route: 'superadmin.coupons.index', patterns: ['superadmin.coupons.*'], permission: 'coupons.view', icon: TicketPercent },
        ],
    },
    {
        title: 'Support',
        items: [
            { label: 'Tickets', route: 'superadmin.tickets.index', patterns: ['superadmin.tickets.*'], permission: 'support.view', icon: Headphones },
        ],
    },
    {
        title: 'Website & CMS',
        items: [
            { label: 'Website overview', route: 'superadmin.website.index', patterns: ['superadmin.website.*'], permission: 'website.view', icon: SlidersHorizontal },
        ],
    },
    {
        title: 'Operations',
        items: [
            { label: 'Provisioning', route: 'superadmin.operations.provisioning', patterns: ['superadmin.operations.provisioning'], permission: 'operations.view', icon: ServerCog },
            { label: 'Reports', route: 'superadmin.reports.index', patterns: ['superadmin.reports.*'], permission: 'dashboard.view', icon: BarChart3 },
            { label: 'Usage', route: 'superadmin.usage.index', patterns: ['superadmin.usage.*'], permission: 'usage.view', icon: BarChart3 },
            { label: 'System health', route: 'superadmin.operations.health', patterns: ['superadmin.operations.health'], permission: 'operations.view', icon: Activity },
        ],
    },
    {
        title: 'AI & LLM',
        items: [
            { label: 'Overview', route: 'superadmin.ai.overview', patterns: ['superadmin.ai.overview'], permission: 'ai.view', icon: Bot },
            { label: 'Providers', route: 'superadmin.ai.providers.index', patterns: ['superadmin.ai.providers.*'], permission: 'ai.providers.view', icon: Cpu },
            { label: 'Models', route: 'superadmin.ai.models.index', patterns: ['superadmin.ai.models.*', 'superadmin.ai.assignments.*'], permission: 'ai.models.view', icon: Sparkles },
            { label: 'Features', route: 'superadmin.ai.features.index', patterns: ['superadmin.ai.features.*'], permission: 'ai.settings.view', icon: ToggleLeft },
            { label: 'Logs', route: 'superadmin.ai.logs.index', patterns: ['superadmin.ai.logs.*'], permission: 'ai.usage.view', icon: ScrollText },
            { label: 'AI Settings', route: 'superadmin.ai.settings.index', patterns: ['superadmin.ai.settings.*'], permission: 'ai.settings.view', icon: SlidersHorizontal },
        ],
    },
    {
        title: 'Security',
        items: [
            { label: 'Platform admins', route: 'superadmin.security.admins.index', patterns: ['superadmin.security.*'], permission: 'administrators.view', icon: ShieldCheck },
        ],
    },
    {
        title: 'Configuration',
        items: [
            { label: 'Feature flags', route: 'superadmin.features.index', patterns: ['superadmin.features.*'], permission: 'features.view', icon: Flag },
            { label: 'Email templates', route: 'superadmin.communications.email-templates.index', patterns: ['superadmin.communications.email-templates.*'], permission: 'communications.view', icon: Mail },
            { label: 'General settings', route: 'superadmin.system.settings.index', patterns: ['superadmin.system.settings.*'], permission: 'settings.view', icon: Settings },
        ],
    },
];

function Brand({ platform, collapsed }) {
    return (
        <Link
            href={route('superadmin.dashboard')}
            className={`flex min-w-0 w-full items-center ${collapsed ? 'justify-center' : 'justify-start'}`}
            aria-label={`${platform?.name || 'PromptBot'} dashboard`}
        >
            <img
                src="/branding/light_logo.png"
                onError={(event) => { event.currentTarget.src = '/branding/logo/light_logo.png'; }}
                alt={platform?.name || 'PromptBot'}
                className={`${collapsed ? 'h-8 max-w-10' : 'h-10 max-w-[190px]'} w-auto max-w-full object-contain`}
            />
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
            className={`flex min-w-0 h-9 items-center gap-2.5 rounded-lg px-2.5 text-[13px] font-medium transition-colors ${collapsed ? 'justify-center' : ''} ${
                active
                    ? 'bg-emerald-500 text-white shadow-sm'
                    : 'text-slate-300 hover:bg-white/10 hover:text-white'
            }`}
        >
            <Icon
                className={`h-4 w-4 shrink-0 ${active ? 'text-white' : 'text-slate-500'}`}
                strokeWidth={1.8}
                aria-hidden="true"
            />
            {!collapsed && <span className="min-w-0 truncate">{item.label}</span>}
        </Link>
    );
}

function Sidebar({ auth, platform, currentRoute, onNavigate, collapsed = false, mobile = false }) {
    return (
        <div className="sa-sidebar-shell flex h-full min-h-0 flex-col bg-slate-950">
            <div className={`flex min-h-[76px] shrink-0 items-center border-b border-slate-200 bg-white ${mobile ? 'pl-4 pr-12' : 'px-4'}`}>
                <Brand platform={platform} collapsed={collapsed} />
            </div>

            <nav
                className="sa-sidebar-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain px-2 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
                aria-label="Superadmin navigation"
            >
                {sections.map((section) => {
                    const items = section.items.filter((item) => can(auth, item.permission));
                    if (!items.length) return null;

                    return (
                        <div key={section.title} className="mb-4 last:mb-0">
                            {!collapsed && (
                                <p className="mb-1 px-2.5 text-[9px] font-bold uppercase tracking-[.16em] text-slate-600">
                                    {section.title}
                                </p>
                            )}

                            <div className="space-y-0.5">
                                {items.map((item) => (
                                    <NavItem
                                        key={item.label}
                                        item={item}
                                        active={matches(currentRoute, item.patterns)}
                                        collapsed={collapsed}
                                        onNavigate={onNavigate}
                                    />
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
                <span className="flex min-w-0 items-center gap-2 px-1">
                    <Avatar name={user?.name} size="sm" />
                    <span className="hidden max-w-32 truncate text-sm font-medium text-slate-700 xl:block">
                        {user?.name || 'Administrator'}
                    </span>
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

function HeaderSearch() {
    const [query, setQuery] = useState('');
    const [mobileSearchOpen, setMobileSearchOpen] = useState(false);
    const desktopInputRef = useRef(null);
    const mobileInputRef = useRef(null);

    const submitSearch = (event) => {
        event.preventDefault();
        const value = query.trim();
        if (!value) return;

        setMobileSearchOpen(false);
        router.get(route('superadmin.search'), { q: value });
    };

    const focusSearch = () => {
        if (window.matchMedia('(min-width: 768px)').matches) {
            desktopInputRef.current?.focus();
            return;
        }

        setMobileSearchOpen(true);
    };

    useEffect(() => {
        if (!mobileSearchOpen) return undefined;

        const frame = window.requestAnimationFrame(() => mobileInputRef.current?.focus());
        return () => window.cancelAnimationFrame(frame);
    }, [mobileSearchOpen]);

    useEffect(() => {
        const handleKeyDown = (event) => {
            const targetTag = document.activeElement?.tagName;
            const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(targetTag) || document.activeElement?.isContentEditable;

            if (event.key === 'Escape' && mobileSearchOpen) {
                setMobileSearchOpen(false);
                return;
            }

            const isSearchShortcut = (
                (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey)
                || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k')
            );

            if (!isSearchShortcut || isTyping) return;

            event.preventDefault();
            focusSearch();
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [mobileSearchOpen]);

    return (
        <div className="relative flex min-w-0 items-center justify-center">
            <form
                onSubmit={submitSearch}
                className="relative hidden min-w-0 w-full max-w-md items-center md:flex"
            >
                <Search className="pointer-events-none absolute left-3 h-4 w-4 text-slate-400" />
                <input
                    ref={desktopInputRef}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    className="min-w-0 w-full rounded-xl border-slate-200 bg-slate-50 py-2 pl-9 pr-12 text-sm transition focus:bg-white"
                    placeholder="Search platform…"
                />
                <span className="pointer-events-none absolute right-2.5 rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[9px] font-semibold text-slate-400">
                    /
                </span>
            </form>

            <button
                type="button"
                onClick={() => setMobileSearchOpen((value) => !value)}
                aria-label="Search platform"
                aria-expanded={mobileSearchOpen}
                aria-controls="mobile-platform-search"
                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900 md:hidden"
            >
                <Search className="h-4 w-4" />
            </button>

            {mobileSearchOpen && (
                <div className="fixed inset-x-3 top-[4.5rem] z-[70] md:hidden">
                    <form
                        id="mobile-platform-search"
                        onSubmit={submitSearch}
                        className="relative mx-auto flex w-full max-w-lg items-center rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl"
                    >
                        <Search className="pointer-events-none absolute left-5 h-4 w-4 text-slate-400" />
                        <input
                            ref={mobileInputRef}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            className="w-full min-w-0 rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-sm"
                            placeholder="Search accounts, services, invoices…"
                        />
                        <button
                            type="button"
                            onClick={() => setMobileSearchOpen(false)}
                            aria-label="Close search"
                            className="absolute right-4 flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </form>
                </div>
            )}
        </div>
    );
}

export default function SuperadminLayout({ header, breadcrumbs, children }) {
    const { auth, flash, platform } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(() => (
        typeof window !== 'undefined'
        && window.localStorage.getItem('sa-sidebar-collapsed') === '1'
    ));

    const currentRoute = route().current();
    const currentItem = sections
        .flatMap((section) => section.items)
        .find((item) => matches(currentRoute, item.patterns));
    const currentSection = sections
        .find((section) => section.items.some((item) => matches(currentRoute, item.patterns)));
    const CurrentIcon = currentItem?.icon || LayoutDashboard;

    useEffect(() => {
        window.localStorage.setItem('sa-sidebar-collapsed', collapsed ? '1' : '0');
    }, [collapsed]);

    useEffect(() => {
        if (!mobileOpen) return undefined;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const handleKeyDown = (event) => {
            if (event.key === 'Escape') setMobileOpen(false);
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [mobileOpen]);

    useEffect(() => {
        const desktopMedia = window.matchMedia('(min-width: 1024px)');
        const closeDrawerOnDesktop = (event) => {
            if (event.matches) setMobileOpen(false);
        };

        desktopMedia.addEventListener?.('change', closeDrawerOnDesktop);
        return () => desktopMedia.removeEventListener?.('change', closeDrawerOnDesktop);
    }, []);

    return (
        <div className="min-h-screen min-w-0 overflow-x-clip bg-[var(--color-bg)] text-slate-900">
            <aside
                className={`fixed inset-y-0 left-0 z-40 hidden border-r border-slate-800 bg-slate-950 shadow-2xl transition-[width] duration-200 lg:block ${
                    collapsed ? 'w-[4.75rem]' : 'w-[15rem]'
                }`}
            >
                <Sidebar
                    auth={auth}
                    platform={platform}
                    currentRoute={currentRoute}
                    collapsed={collapsed}
                />

                <button
                    type="button"
                    onClick={() => setCollapsed((value) => !value)}
                    aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    className="absolute -right-3 top-20 flex h-6 w-6 items-center justify-center rounded-full border border-slate-700 bg-slate-900 text-slate-400 shadow-soft hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                >
                    {collapsed
                        ? <ChevronsRight className="h-3.5 w-3.5" />
                        : <ChevronsLeft className="h-3.5 w-3.5" />}
                </button>
            </aside>

            <div
                className={`fixed inset-0 z-50 lg:hidden ${mobileOpen ? 'pointer-events-auto' : 'pointer-events-none'}`}
                aria-hidden={!mobileOpen}
            >
                <button
                    type="button"
                    aria-label="Close navigation"
                    onClick={() => setMobileOpen(false)}
                    className={`absolute inset-0 bg-slate-950/55 backdrop-blur-[1px] transition-opacity duration-200 ${
                        mobileOpen ? 'opacity-100' : 'opacity-0'
                    }`}
                />

                <aside
                    role="dialog"
                    aria-modal="true"
                    aria-label="Superadmin navigation"
                    className={`absolute inset-y-0 left-0 h-dvh w-[min(17rem,calc(100vw-2rem))] transform-gpu border-r border-slate-800 bg-slate-950 shadow-2xl transition-transform duration-200 ease-out ${
                        mobileOpen ? 'translate-x-0' : '-translate-x-full'
                    }`}
                >
                    <button
                        type="button"
                        aria-label="Close menu"
                        onClick={() => setMobileOpen(false)}
                        className="absolute right-3 top-[22px] z-10 flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                    >
                        <X className="h-4 w-4" />
                    </button>

                    <Sidebar
                        auth={auth}
                        platform={platform}
                        currentRoute={currentRoute}
                        onNavigate={() => setMobileOpen(false)}
                        mobile
                    />
                </aside>
            </div>

            <div
                className={`min-h-screen min-w-0 transition-[padding] duration-200 ${
                    collapsed ? 'lg:pl-[4.75rem]' : 'lg:pl-[15rem]'
                }`}
            >
                <header className="sticky top-0 z-30 grid min-h-[64px] grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-1.5 border-b border-slate-200 bg-white/95 px-2.5 py-2 shadow-sm shadow-slate-950/[.02] backdrop-blur-xl min-[380px]:px-3 sm:gap-2 sm:px-4 md:grid-cols-[minmax(0,1fr)_minmax(12rem,28rem)_minmax(0,1fr)] lg:px-5">
                    <div className="flex min-w-0 items-center gap-2 min-[380px]:gap-2.5 sm:gap-3">
                        <button
                            type="button"
                            aria-label="Open navigation"
                            aria-expanded={mobileOpen}
                            onClick={() => setMobileOpen(true)}
                            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 lg:hidden"
                        >
                            <Menu className="h-4 w-4" />
                        </button>

                        <span
                            className="hidden h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 sm:flex"
                            aria-hidden="true"
                        >
                            <CurrentIcon className="h-4 w-4" strokeWidth={1.8} />
                        </span>

                        <div className="hidden min-w-0 min-[360px]:block">
                            <p className="hidden truncate text-[9px] font-bold uppercase tracking-[.14em] text-emerald-700 sm:block">
                                {currentSection?.title || 'Platform'}
                            </p>
                            <p className="max-w-[7rem] truncate text-sm font-semibold text-slate-900 min-[430px]:max-w-[10rem] sm:max-w-xs">
                                {currentItem?.label || 'Superadmin dashboard'}
                            </p>
                            {breadcrumbs && (
                                <div className="hidden max-w-sm truncate text-[10px] text-slate-400 xl:block">
                                    {breadcrumbs}
                                </div>
                            )}
                        </div>
                    </div>

                    <HeaderSearch />

                    <div className="flex min-w-0 justify-end">
                        <div className="shrink-0 rounded-xl border border-slate-200 bg-slate-50/80 px-1 py-1 shadow-sm sm:px-1.5">
                            <UserMenu user={auth?.user} />
                        </div>
                    </div>
                </header>

                <main className="min-w-0 px-3 py-4 sm:px-5 sm:py-6 lg:px-6">
                    {flash?.status && <Alert tone="success" className="mb-4">{flash.status}</Alert>}
                    {flash?.error && <Alert tone="danger" className="mb-4">{flash.error}</Alert>}
                    {header && <div className="mb-4 min-w-0 sm:mb-6">{header}</div>}
                    <div className="min-w-0 max-w-full">{children}</div>
                </main>
            </div>
        </div>
    );
}
