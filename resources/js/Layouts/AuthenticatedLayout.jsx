import Alert from '@/Components/UI/Alert';
import Avatar from '@/Components/UI/Avatar';
import BrandLogo from '@/Components/BrandLogo';
import SuperadminLayout from '@/Layouts/SuperadminLayout';
import { Menu as HeadlessMenu, Transition } from '@headlessui/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    Cable,
    CalendarClock,
    CheckSquare,
    CircleUserRound,
    Code2,
    Gauge,
    Globe2,
    Inbox,
    LayoutDashboard,
    Library,
    LogOut,
    Menu,
    Search,
    ShieldCheck,
    Sparkles,
    Star,
    TicketCheck,
    UsersRound,
    Workflow,
    X,
} from 'lucide-react';
import { Fragment, useState } from 'react';


const DEFAULT_TENANT_PRIMARY = '#059669';

function tenantBrandVariables(primaryColor, secondaryColor) {
    const primary = /^#[0-9A-Fa-f]{6}$/.test(primaryColor ?? '')
        ? primaryColor
        : DEFAULT_TENANT_PRIMARY;
    const secondary = /^#[0-9A-Fa-f]{6}$/.test(secondaryColor ?? '')
        ? secondaryColor
        : '#0F172A';
    const rgb = (hex) => [1, 3, 5].map((offset) => Number.parseInt(hex.slice(offset, offset + 2), 16));
    const mix = (hex, target, amount) => rgb(hex)
        .map((channel, index) => Math.round(channel + (target[index] - channel) * amount))
        .join(' ');

    return {
        '--brand-50': mix(primary, [255, 255, 255], 0.94),
        '--brand-100': mix(primary, [255, 255, 255], 0.86),
        '--brand-200': mix(primary, [255, 255, 255], 0.72),
        '--brand-300': mix(primary, [255, 255, 255], 0.54),
        '--brand-400': mix(primary, [255, 255, 255], 0.28),
        '--brand-500': mix(primary, [255, 255, 255], 0.12),
        '--brand-600': rgb(primary).join(' '),
        '--brand-700': mix(primary, [0, 0, 0], 0.15),
        '--brand-800': mix(primary, [0, 0, 0], 0.3),
        '--brand-900': mix(primary, [0, 0, 0], 0.45),
        '--focus-ring': primary,
        '--tenant-secondary': secondary,
    };
}


const primaryNavigation = [
    {
        label: 'Dashboard',
        routeName: 'tenant.admin.dashboard',
        active: 'tenant.admin.dashboard',
        icon: LayoutDashboard,
    },
    {
        label: 'Inbox',
        routeName: 'tenant.admin.inbox.index',
        active: 'tenant.admin.inbox.*',
        icon: Inbox,
        permission: 'inbox.view',
    },
    {
        label: 'Tickets',
        routeName: 'tenant.admin.tickets.index',
        active: 'tenant.admin.tickets.*',
        icon: TicketCheck,
        permission: 'tickets.view',
    },
    {
        label: 'Tasks',
        routeName: 'tenant.admin.tasks.index',
        active: 'tenant.admin.tasks.*',
        icon: CheckSquare,
        permission: 'tasks.view',
    },
    {
        label: 'Customers',
        routeName: 'tenant.admin.customers.index',
        active: 'tenant.admin.customers.*',
        icon: UsersRound,
        permissions: ['customers.view', 'companies.view', 'customers.import', 'tags.manage', 'custom_fields.manage'],
    },
    {
        label: 'AI & engagement',
        destinations: [
            { permission: 'channels.view', routeName: 'tenant.admin.channels.index' },
            { permission: 'experience.view', routeName: 'tenant.admin.experience.index' },
        ],
        active: ['tenant.admin.channels.*', 'tenant.admin.experience.*'],
        icon: Globe2,
        permissions: ['channels.view', 'experience.view'],
    },
    {
        label: 'Knowledge base',
        routeName: 'tenant.admin.knowledge.index',
        active: 'tenant.admin.knowledge.*',
        icon: Library,
        permission: 'knowledge.view',
    },
    {
        label: 'AI',
        routeName: 'tenant.admin.ai.index',
        active: 'tenant.admin.ai.*',
        icon: Sparkles,
        permission: 'ai.view',
    },
    {
        label: 'Operations',
        destinations: [
            { permission: 'operations.view', routeName: 'tenant.admin.operations.index' },
            { permission: 'automation.view', routeName: 'tenant.admin.automation.index' },
            { permission: 'reports.view', routeName: 'tenant.admin.reports.index' },
            { permission: 'quality.view', routeName: 'tenant.admin.quality.index' },
            { permission: 'workforce.view', routeName: 'tenant.admin.workforce.index' },
        ],
        active: ['tenant.admin.operations.*', 'tenant.admin.automation.*', 'tenant.admin.reports.*', 'tenant.admin.quality.*', 'tenant.admin.workforce.*'],
        icon: Gauge,
        permissions: ['operations.view', 'automation.view', 'reports.view', 'quality.view', 'workforce.view'],
    },
    {
        label: 'Platform',
        destinations: [
            { permission: 'connections.view', routeName: 'tenant.admin.connections.overview' },
            { permission: 'governance.view', routeName: 'tenant.admin.governance.index' },
        ],
        active: ['tenant.admin.connections.*', 'tenant.admin.governance.*'],
        icon: Cable,
        permissions: ['connections.view', 'governance.view'],
    },
];

const administrationItem = {
    label: 'Administration',
    destinations: [
        { permission: 'users.view', routeName: 'tenant.admin.administration.index' },
        { permission: 'invitations.view', routeName: 'tenant.admin.administration.invitations.index' },
        { permission: 'teams.view', routeName: 'tenant.admin.administration.teams.index' },
        { permission: 'departments.view', routeName: 'tenant.admin.administration.departments.index' },
        { permission: 'roles.view', routeName: 'tenant.admin.administration.roles.index' },
        { permission: 'workspace.view', routeName: 'tenant.admin.administration.workspace.edit' },
    ],
    active: 'tenant.admin.administration.*',
    icon: ShieldCheck,
    permissions: ['users.view', 'invitations.view', 'teams.view', 'departments.view', 'roles.view', 'workspace.view'],
};


function Brand({ tenant }) {
    if (!tenant?.logoUrl) {
        return (
            <Link href={route('tenant.admin.dashboard')} className="flex min-w-0 items-center">
                <BrandLogo className="h-8 w-auto max-w-[10.5rem]" />
            </Link>
        );
    }

    return (
        <Link
            href={route('tenant.admin.dashboard')}
            className="flex min-w-0 items-center gap-2"
        >
            <span
                className="flex h-7 w-full shrink-0 items-center justify-center overflow-hidden rounded-md text-white"
                style={{ backgroundColor: 'var(--tenant-secondary)' }}
            >
                <img
                    src={tenant.logoUrl}
                    alt=""
                    className="h-full w-full object-contain p-1"
                />
            </span>

             
        </Link>
    );
}


function NavItem({ item, onNavigate }) {
    const Icon = item.icon;
    const permissions = usePage().props.auth?.permissions || [];
    const routeName = item.destinations?.find((destination) => permissions.includes(destination.permission))?.routeName || item.routeName;
    const patterns = Array.isArray(item.active) ? item.active : [item.active];
    const isActive = patterns.some((pattern) => route().current(pattern));

    return (
        <Link
            href={route(routeName)}
            onClick={onNavigate}
            aria-current={isActive ? 'page' : undefined}
            className={`flex min-h-10 min-w-0 items-center gap-2.5 rounded-lg px-3 text-sm font-medium transition-colors ${
                isActive
                    ? 'bg-brand-50 text-brand-800'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-navy-900'
            }`}
        >
            <Icon
                className={`h-[18px] w-[18px] shrink-0 ${
                    isActive ? 'text-brand-600' : 'text-slate-400'
                }`}
                strokeWidth={1.8}
                aria-hidden="true"
            />

            <span className="min-w-0 truncate">
                {item.label}
            </span>
        </Link>
    );
}

const workspaceNavigation = [
    {
        patterns: ['tenant.admin.operations.*', 'tenant.admin.automation.*', 'tenant.admin.reports.*', 'tenant.admin.quality.*', 'tenant.admin.workforce.*'],
        label: 'Operations workspace',
        items: [
            { label: 'Operations', routeName: 'tenant.admin.operations.index', active: 'tenant.admin.operations.*', permission: 'operations.view', icon: Gauge },
            { label: 'Automation', routeName: 'tenant.admin.automation.index', active: 'tenant.admin.automation.*', permission: 'automation.view', icon: Workflow },
            { label: 'Reports', routeName: 'tenant.admin.reports.index', active: 'tenant.admin.reports.*', permission: 'reports.view', icon: BarChart3 },
            { label: 'Quality', routeName: 'tenant.admin.quality.index', active: 'tenant.admin.quality.*', permission: 'quality.view', icon: Star },
            { label: 'Workforce', routeName: 'tenant.admin.workforce.index', active: 'tenant.admin.workforce.*', permission: 'workforce.view', icon: CalendarClock },
        ],
    },
    {
        patterns: ['tenant.admin.connections.*', 'tenant.admin.governance.*'],
        label: 'Platform workspace',
        items: [
            { label: 'Connections', routeName: 'tenant.admin.connections.overview', active: 'tenant.admin.connections.*', permission: 'connections.view', icon: Cable },
            { label: 'Developer', routeName: 'tenant.admin.governance.index', active: 'tenant.admin.governance.*', permission: 'governance.view', icon: Code2 },
        ],
    },
];

function WorkspaceNavigation({ permissions = [] }) {
    const workspace = workspaceNavigation.find((group) => group.patterns.some((pattern) => route().current(pattern)));
    if (!workspace) return null;
    const items = workspace.items.filter((item) => permissions.includes(item.permission));
    return <nav className="mb-5 flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white px-2 shadow-sm" aria-label={workspace.label}>{items.map((item) => { const Icon = item.icon; const active = route().current(item.active); return <Link key={item.routeName} href={route(item.routeName)} className={`flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-3 text-xs font-semibold transition ${active ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'}`}><Icon className="h-3.5 w-3.5" />{item.label}</Link>; })}</nav>;
}

function Sidebar({ tenant, user, avatarUrl, onNavigate }) {
    const { auth } = usePage().props;
    const canSee = (item) => item.permissions
        ? item.permissions.some((permission) => auth?.permissions?.includes(permission))
        : !item.permission || auth?.permissions?.includes(item.permission);
    const visiblePrimaryNavigation = primaryNavigation.filter(canSee);

    return (
        <div className="flex h-full flex-col bg-white">

            {/* Sidebar header */}
            <div className="flex h-header items-center border-b border-slate-200 px-3 pr-12 lg:pr-3">
                <Brand tenant={tenant} />
            </div>

            {/* Sidebar navigation */}
            <div className="sidebar-scroll min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain px-2 py-2.5">
                <nav
                    className="space-y-1"
                    aria-label="Tenant navigation"
                >
                    {visiblePrimaryNavigation.map((item) => (
                        <NavItem
                            key={item.label}
                            item={item}
                            onNavigate={onNavigate}
                        />
                    ))}

                </nav>
            </div>

            {/* Sidebar account */}
            <div className="border-t border-slate-200 p-2">
                {canSee(administrationItem) && <NavItem item={administrationItem} onNavigate={onNavigate} />}
            </div>
            <div className="border-t border-slate-100 p-2.5">
                <div className="flex min-w-0 items-center gap-2.5 px-1">

                    <div className="shrink-0">
                        <Avatar
                            name={user?.name}
                            src={avatarUrl}
                            size="sm"
                        />
                    </div>

                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-medium text-slate-700">
                            {user?.name || 'Team member'}
                        </span>

                        <span className="block truncate text-xs text-slate-400">
                            {user?.email}
                        </span>
                    </span>

                </div>
            </div>

        </div>
    );
}


/**
 * Navbar User Menu
 *
 * Only the avatar is visible in the navbar.
 * Username/email are displayed inside the dropdown.
 */
function UserMenu({ user, avatarUrl }) {
    return (
        <HeadlessMenu
            as="div"
            className="relative shrink-0"
        >

            <HeadlessMenu.Button
                type="button"
                aria-label="Open user menu"
                className="
                    flex h-9 w-9 shrink-0
                    items-center justify-center
                    rounded-full
                    transition-colors
                    hover:bg-slate-100
                    focus-visible:outline-none
                    focus-visible:ring-2
                    focus-visible:ring-brand-500
                    focus-visible:ring-offset-1
                "
            >
                <span className="flex shrink-0 items-center justify-center">
                    <Avatar
                        name={user?.name}
                        src={avatarUrl}
                        size="sm"
                    />
                </span>
            </HeadlessMenu.Button>


            <Transition
                as={Fragment}
                enter="transition ease-out duration-100"
                enterFrom="translate-y-1 opacity-0"
                enterTo="translate-y-0 opacity-100"
                leave="transition ease-in duration-75"
                leaveFrom="opacity-100"
                leaveTo="opacity-0"
            >

                <HeadlessMenu.Items
                    className="
                        absolute right-0 z-50 mt-2
                        w-64
                        overflow-hidden
                        rounded-lg
                        border border-slate-200
                        bg-white
                        shadow-soft-lg
                        focus:outline-none
                    "
                >

                    {/* Account details */}
                    <div className="border-b border-slate-100 px-3 py-2.5">
                        <p className="truncate text-sm font-semibold text-slate-800">
                            {user?.name || 'Team member'}
                        </p>

                        <p className="mt-0.5 truncate text-xs text-slate-500">
                            {user?.email || 'Signed in'}
                        </p>
                    </div>


                    {/* Actions */}
                    <div className="p-1.5">
                        <HeadlessMenu.Item>
                            {({ active }) => (
                                <Link
                                    href={route('tenant.admin.profile.edit')}
                                    className={`flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm text-slate-700 ${active ? 'bg-slate-50' : ''}`}
                                >
                                    <CircleUserRound className="h-4 w-4 shrink-0" aria-hidden="true" />
                                    <span>My profile</span>
                                </Link>
                            )}
                        </HeadlessMenu.Item>

                        <div className="my-1 h-px bg-slate-100" />

                        <HeadlessMenu.Item>
                            {({ active }) => (
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post(
                                            route('tenant.logout'),
                                        )
                                    }
                                    className={`
                                        flex w-full
                                        items-center gap-2
                                        rounded-md
                                        px-2.5 py-2
                                        text-left text-sm
                                        text-rose-600
                                        ${
                                            active
                                                ? 'bg-rose-50'
                                                : ''
                                        }
                                    `}
                                >
                                    <LogOut
                                        className="h-4 w-4 shrink-0"
                                        aria-hidden="true"
                                    />

                                    <span>
                                        Log out
                                    </span>
                                </button>
                            )}
                        </HeadlessMenu.Item>
                    </div>

                </HeadlessMenu.Items>

            </Transition>

        </HeadlessMenu>
    );
}


function NavbarSearch() {
    const [query, setQuery] = useState('');

    const submit = (event) => {
        event.preventDefault();

        const value = query.trim();

        if (value) {
            router.get(
                route('tenant.admin.search'),
                { q: value },
            );
        }
    };

    return (
        <form
            onSubmit={submit}
            role="search"
            className="relative w-full"
        >
            <Search
                className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
            />

            <input
                type="search"
                value={query}
                onChange={(event) =>
                    setQuery(event.target.value)
                }
                placeholder="Search contacts, tickets, conversations…"
                aria-label="Search workspace"
                className="
                    h-9 w-full
                    rounded-lg
                    border border-slate-300
                    bg-slate-50
                    pl-9 pr-3
                    text-sm text-slate-900
                    placeholder:text-slate-400
                    shadow-sm
                    focus:border-brand-500
                    focus:bg-white
                    focus:ring-brand-500
                "
            />
        </form>
    );
}


function notificationContent(notification) {
    if (
        typeof notification.data === 'object' &&
        notification.data !== null
    ) {
        return notification.data;
    }

    try {
        return JSON.parse(notification.data || '{}');
    } catch {
        return {};
    }
}


function NotificationMenu({ notifications }) {
    const recent = notifications?.recent || [];
    const unreadCount = notifications?.unreadCount || 0;

    const markRead = (id) =>
        router.put(
            route(
                'tenant.admin.notifications.read',
                id,
            ),
            {},
            {
                preserveScroll: true,
            },
        );

    const markAllRead = () =>
        router.put(
            route(
                'tenant.admin.notifications.read-all',
            ),
            {},
            {
                preserveScroll: true,
            },
        );

    return (
        <HeadlessMenu
            as="div"
            className="relative shrink-0"
        >

            {/* Notification button */}
            <HeadlessMenu.Button
                aria-label={`Notifications${
                    unreadCount
                        ? `, ${unreadCount} unread`
                        : ''
                }`}
                className="
                    relative
                    flex h-9 w-9 shrink-0
                    items-center justify-center
                    rounded-md
                    text-slate-500
                    transition-colors
                    hover:bg-slate-100
                    hover:text-slate-900
                    focus-visible:outline-none
                    focus-visible:ring-2
                    focus-visible:ring-brand-500
                "
            >
                <Bell
                    className="h-[18px] w-[18px]"
                    aria-hidden="true"
                />

                {unreadCount > 0 && (
                    <span
                        className="
                            absolute right-0 top-0
                            min-w-[17px]
                            rounded-full
                            bg-rose-600
                            px-1
                            text-center
                            text-[10px]
                            font-bold
                            leading-[17px]
                            text-white
                        "
                    >
                        {unreadCount > 99
                            ? '99+'
                            : unreadCount}
                    </span>
                )}
            </HeadlessMenu.Button>


            {/* Notification dropdown */}
            <Transition
                as={Fragment}
                enter="transition ease-out duration-100"
                enterFrom="translate-y-1 opacity-0"
                enterTo="translate-y-0 opacity-100"
                leave="transition ease-in duration-75"
                leaveFrom="opacity-100"
                leaveTo="opacity-0"
            >
                <HeadlessMenu.Items
                    className="
                        absolute right-0 z-50 mt-2
                        w-[min(22rem,calc(100vw-1.5rem))]
                        overflow-hidden
                        rounded-lg
                        border border-slate-200
                        bg-white
                        shadow-soft-lg
                        focus:outline-none
                    "
                >

                    {/* Header */}
                    <div className="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">

                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-slate-900">
                                Notifications
                            </p>

                            <p className="truncate text-xs text-slate-500">
                                {unreadCount
                                    ? `${unreadCount} unread`
                                    : 'You are all caught up'}
                            </p>
                        </div>


                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={markAllRead}
                                className="
                                    shrink-0
                                    whitespace-nowrap
                                    text-xs
                                    font-semibold
                                    text-brand-700
                                    hover:text-brand-800
                                "
                            >
                                Mark all read
                            </button>
                        )}

                    </div>


                    {/* Notification list */}
                    <div className="max-h-80 overflow-y-auto">

                        {recent.length ? (
                            recent.map(
                                (notification) => {
                                    const data =
                                        notificationContent(
                                            notification,
                                        );

                                    return (
                                        <HeadlessMenu.Item
                                            key={
                                                notification.id
                                            }
                                        >
                                            {({
                                                active,
                                            }) => (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        markRead(
                                                            notification.id,
                                                        )
                                                    }
                                                    className={`
                                                        flex w-full
                                                        gap-3
                                                        border-b
                                                        border-slate-100
                                                        px-4 py-3
                                                        text-left
                                                        last:border-0
                                                        ${
                                                            active
                                                                ? 'bg-slate-50'
                                                                : ''
                                                        }
                                                    `}
                                                >
                                                    <span
                                                        className={`
                                                            mt-1
                                                            h-2 w-2
                                                            shrink-0
                                                            rounded-full
                                                            ${
                                                                notification.read_at
                                                                    ? 'bg-slate-200'
                                                                    : 'bg-brand-500'
                                                            }
                                                        `}
                                                    />

                                                    <span className="min-w-0 flex-1">

                                                        <span className="block truncate text-sm font-medium text-slate-800">
                                                            {data.title ||
                                                                notification.type.replaceAll(
                                                                    '_',
                                                                    ' ',
                                                                )}
                                                        </span>

                                                        <span className="mt-0.5 block line-clamp-2 text-xs leading-5 text-slate-500">
                                                            {data.message ||
                                                                data.policy ||
                                                                'Open notifications for more details.'}
                                                        </span>

                                                    </span>
                                                </button>
                                            )}
                                        </HeadlessMenu.Item>
                                    );
                                },
                            )
                        ) : (
                            <p className="px-4 py-8 text-center text-sm text-slate-500">
                                No notifications yet.
                            </p>
                        )}

                    </div>


                    <Link
                        href={route(
                            'tenant.admin.notifications.index',
                        )}
                        className="
                            block
                            border-t border-slate-100
                            px-4 py-3
                            text-center
                            text-xs
                            font-semibold
                            text-brand-700
                            hover:bg-slate-50
                        "
                    >
                        View all notifications
                    </Link>

                </HeadlessMenu.Items>
            </Transition>

        </HeadlessMenu>
    );
}


export default function AuthenticatedLayout({
    header,
    title,
    children,
}) {
    const { auth, tenant, flash } = usePage().props;
    const [mobileOpen, setMobileOpen] =
        useState(false);


    /*
     * Superadmin layout
     */
    if (auth?.guard !== 'tenant') {
        return (
            <SuperadminLayout header={header}>
                {children}
            </SuperadminLayout>
        );
    }


    const user = auth.user;

    const canSearch =
        auth.permissions?.includes('customers.view') ||
        auth.permissions?.includes('inbox.view');


    return (
        <div
            style={tenantBrandVariables(tenant?.primaryColor, tenant?.secondaryColor)}
            className="
                min-h-screen
                w-full
                max-w-full
                overflow-x-hidden
                bg-[var(--color-bg)]
                text-slate-900
            "
        >

            <Head>
                {tenant?.faviconUrl && <link rel="icon" href={tenant.faviconUrl} />}
                <meta name="theme-color" content={tenant?.primaryColor || DEFAULT_TENANT_PRIMARY} />
            </Head>

            {/* Sleek sidebar scrollbar */}
            <style>{`
                .sidebar-scroll {
                    scrollbar-width: thin;
                    scrollbar-color: transparent transparent;
                }

                .sidebar-scroll:hover {
                    scrollbar-color: rgb(203 213 225) transparent;
                }

                .sidebar-scroll::-webkit-scrollbar {
                    width: 5px;
                }

                .sidebar-scroll::-webkit-scrollbar-track {
                    background: transparent;
                }

                .sidebar-scroll::-webkit-scrollbar-thumb {
                    border-radius: 9999px;
                    background-color: transparent;
                    transition: background-color 150ms ease;
                }

                .sidebar-scroll:hover::-webkit-scrollbar-thumb {
                    background-color: rgb(203 213 225);
                }

                .sidebar-scroll:hover::-webkit-scrollbar-thumb:hover {
                    background-color: rgb(148 163 184);
                }
            `}</style>


            {/* =========================
                DESKTOP SIDEBAR
            ========================== */}

            <aside
                className="
                    fixed inset-y-0 left-0 z-40
                    hidden
                    w-52
                    border-r border-slate-200
                    bg-white
                    lg:block
                "
            >
                <Sidebar
                    tenant={tenant}
                    user={user}
                    avatarUrl={auth.avatarUrl}
                />
            </aside>


            {/* =========================
                MOBILE SIDEBAR
            ========================== */}

            <div
                className={`
                    fixed inset-0 z-50 lg:hidden
                    ${
                        mobileOpen
                            ? 'pointer-events-auto'
                            : 'pointer-events-none'
                    }
                `}
                aria-hidden={!mobileOpen}
            >

                {/* Overlay */}
                <button
                    type="button"
                    aria-label="Close navigation"
                    onClick={() =>
                        setMobileOpen(false)
                    }
                    className={`
                        absolute inset-0
                        bg-navy-950/50
                        transition-opacity
                        ${
                            mobileOpen
                                ? 'opacity-100'
                                : 'opacity-0'
                        }
                    `}
                />


                {/* Mobile sidebar */}
                <aside
                    className={`
                        relative
                        h-full
                        w-[min(17rem,calc(100vw-2rem))]
                        border-r border-slate-200
                        bg-white
                        shadow-soft-lg
                        transition-transform
                        duration-200
                        ease-out
                        ${
                            mobileOpen
                                ? 'translate-x-0'
                                : '-translate-x-full'
                        }
                    `}
                >

                    <button
                        type="button"
                        aria-label="Close menu"
                        onClick={() =>
                            setMobileOpen(false)
                        }
                        className="
                            absolute right-3 top-3 z-10
                            flex h-8 w-8
                            items-center justify-center
                            rounded-md
                            text-slate-500
                            transition-colors
                            hover:bg-slate-100
                            hover:text-slate-900
                        "
                    >
                        <X className="h-4 w-4" />
                    </button>


                    <Sidebar
                        tenant={tenant}
                        user={user}
                        avatarUrl={auth.avatarUrl}
                        onNavigate={() =>
                            setMobileOpen(false)
                        }
                    />

                </aside>
            </div>


            {/* =========================
                APPLICATION
            ========================== */}

            <div
                className="
                    min-h-screen
                    w-full
                    min-w-0
                    max-w-full
                    overflow-x-hidden
                    lg:pl-52
                "
            >

                {/* =========================
                    NAVBAR
                ========================== */}

                <header
                    className="
                        sticky top-0 z-30
                        flex h-header
                        w-full
                        min-w-0
                        max-w-full
                        items-center
                        border-b border-slate-200
                        bg-white/95
                        px-3
                        backdrop-blur
                        sm:px-4
                        lg:px-5
                        xl:px-6
                    "
                >

                    {/* LEFT */}
                    <div className="flex min-w-0 flex-1 items-center gap-2 xl:max-w-[calc(50%-18rem)]">

                        {/* Mobile hamburger */}
                        <button
                            type="button"
                            aria-label="Open navigation"
                            aria-expanded={
                                mobileOpen
                            }
                            onClick={() =>
                                setMobileOpen(
                                    true,
                                )
                            }
                            className="
                                flex h-9 w-9 shrink-0
                                items-center justify-center
                                rounded-md
                                text-slate-600
                                transition-colors
                                hover:bg-slate-100
                                hover:text-slate-900
                                lg:hidden
                            "
                        >
                            <Menu className="h-4 w-4" />
                        </button>


                        {/* Page title */}
                        <p
                            className="
                                min-w-0
                                flex-1
                                truncate
                                text-sm
                                font-semibold
                                text-navy-900
                            "
                        >
                            {title ||
                                'Workspace'}
                        </p>

                    </div>


                    {/* DESKTOP SEARCH */}
                    {canSearch && (
                        <div
                            className="
                                absolute left-1/2 top-1/2
                                hidden
                                w-[clamp(20rem,34vw,34rem)]
                                -translate-x-1/2 -translate-y-1/2
                                xl:block
                            "
                        >
                            <NavbarSearch />
                        </div>
                    )}


                    {/* RIGHT */}
                    <div
                        className="
                            ml-auto
                            flex shrink-0
                            items-center
                            justify-end
                            gap-1
                            sm:gap-1.5
                        "
                    >

                        {/* Search icon */}
                        {canSearch && (
                            <Link
                                href={route(
                                    'tenant.admin.search',
                                )}
                                aria-label="Search workspace"
                                className="
                                    flex h-9 w-9 shrink-0
                                    items-center
                                    justify-center
                                    rounded-md
                                    text-slate-500
                                    transition-colors
                                    hover:bg-slate-100
                                    hover:text-slate-900
                                    xl:hidden
                                "
                            >
                                <Search className="h-[18px] w-[18px]" />
                            </Link>
                        )}


                        {/* Notifications */}
                        <NotificationMenu
                            notifications={
                                tenant?.notifications
                            }
                        />


                        {/* Divider */}
                        <div
                            className="
                                mx-0.5
                                hidden
                                h-5 w-px
                                shrink-0
                                bg-slate-200
                                sm:block
                            "
                            aria-hidden="true"
                        />


                        {/* AVATAR ONLY */}
                        <UserMenu
                            user={user}
                            avatarUrl={auth.avatarUrl}
                        />

                    </div>
                </header>


                {/* =========================
                    MAIN CONTENT
                ========================== */}

                <main
                    className="
                        w-full
                        min-w-0
                        max-w-full
                        overflow-x-hidden
                        px-3 py-4
                        sm:px-4
                        sm:py-5
                        lg:px-5
                        xl:px-6
                        xl:py-6
                    "
                >

                    {flash?.status && (
                        <Alert
                            tone="success"
                            className="mb-4"
                        >
                            {flash.status}
                        </Alert>
                    )}


                    {flash?.error && (
                        <Alert
                            tone="danger"
                            className="mb-4"
                        >
                            {flash.error}
                        </Alert>
                    )}


                    {header && (
                        <div className="mb-5 min-w-0 sm:mb-6">
                            {header}
                        </div>
                    )}

                    <WorkspaceNavigation permissions={auth.permissions || []} />


                    <div className="min-w-0 max-w-full">
                        {children}
                    </div>

                </main>

            </div>
        </div>
    );
}
