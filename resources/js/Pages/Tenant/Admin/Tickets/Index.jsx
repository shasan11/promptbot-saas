import Pagination from '@/Components/Superadmin/Pagination';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

import {
    Head,
    Link,
    router,
    usePage,
} from '@inertiajs/react';

import {
    Archive,
    CheckCircle2,
    CircleDot,
    CircleUserRound,
    Clock3,
    Inbox,
    Plus,
    SlidersHorizontal,
    TicketCheck,
    UserRoundX,
} from 'lucide-react';

import { useState } from 'react';


const views = [
    {
        key: 'all',
        label: 'All tickets',
        icon: TicketCheck,
    },
    {
        key: 'mine',
        label: 'My tickets',
        icon: CircleUserRound,
    },
    {
        key: 'unassigned',
        label: 'Unassigned',
        icon: UserRoundX,
    },
    {
        key: 'open',
        label: 'Open',
        icon: CircleDot,
    },
    {
        key: 'pending',
        label: 'Pending',
        icon: Clock3,
    },
    {
        key: 'resolved',
        label: 'Resolved',
        icon: CheckCircle2,
    },
    {
        key: 'closed',
        label: 'Closed',
        icon: Archive,
    },
];


const priorityTone = {
    urgent: 'danger',
    high: 'warning',
    normal: 'neutral',
    low: 'neutral',
};


const getStatusTone = (category) => {
    if (['resolved', 'closed'].includes(category)) {
        return 'brand';
    }

    if (category === 'pending') {
        return 'warning';
    }

    if (category === 'open') {
        return 'info';
    }

    return 'neutral';
};


const formatDate = (date) => {
    if (!date) return '';

    const value = new Date(date);
    const now = new Date();

    const sameDay =
        value.getDate() === now.getDate() &&
        value.getMonth() === now.getMonth() &&
        value.getFullYear() === now.getFullYear();

    if (sameDay) {
        return value.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);

    const isYesterday =
        value.getDate() === yesterday.getDate() &&
        value.getMonth() === yesterday.getMonth() &&
        value.getFullYear() === yesterday.getFullYear();

    if (isYesterday) {
        return 'Yesterday';
    }

    return value.toLocaleDateString([], {
        month: 'short',
        day: 'numeric',
    });
};


export default function Index({
    tickets,
    filters,
    statuses,
    teams,
}) {
    const permissions =
        usePage().props.auth?.permissions || [];

    const [search, setSearch] = useState(
        filters.search || '',
    );

    const activeView = filters.view || 'all';


    const apply = (next = {}) => {
        router.get(
            route('tenant.admin.tickets.index'),
            {
                ...filters,
                search,
                ...next,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };


    const submitSearch = (event) => {
        event.preventDefault();

        apply({
            search: search.trim(),
        });
    };


    return (
        <AuthenticatedLayout
            title="Tickets"
            header={
                <div
                    className="
                        flex flex-col gap-4
                        sm:flex-row
                        sm:items-center
                        sm:justify-between
                    "
                >
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold text-navy-900">
                            Tickets
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Track structured support work through resolution.
                        </p>
                    </div>


                    {permissions.includes(
                        'tickets.create',
                    ) && (
                        <div className="shrink-0">
                            <Button
                                href={route(
                                    'tenant.admin.tickets.create',
                                )}
                                variant="brand"
                                icon={Plus}
                            >
                                Create ticket
                            </Button>
                        </div>
                    )}
                </div>
            }
        >
            <Head title="Tickets" />


            <div
                className="
                    overflow-hidden
                    rounded-xl
                    border border-slate-200
                    bg-white
                    shadow-sm
                "
            >
                <div
                    className="
                        grid
                        min-h-[calc(100vh-11rem)]
                        lg:grid-cols-[190px_minmax(0,1fr)]
                    "
                >

                    {/* =========================
                        TICKET VIEWS
                    ========================== */}

                    <aside
                        className="
                            border-b border-slate-200
                            bg-slate-50/70
                            lg:border-b-0
                            lg:border-r
                        "
                    >

                        {/* Desktop sidebar heading */}
                        <div className="hidden px-4 pb-2 pt-5 lg:block">
                            <div className="flex items-center gap-2">

                                <div
                                    className="
                                        flex h-8 w-8
                                        items-center justify-center
                                        rounded-lg
                                        bg-brand-50
                                        text-brand-700
                                    "
                                >
                                    <TicketCheck className="h-4 w-4" />
                                </div>


                                <div>
                                    <p className="text-sm font-bold text-navy-900">
                                        Ticket views
                                    </p>

                                    <p className="text-[11px] text-slate-400">
                                        Manage support work
                                    </p>
                                </div>

                            </div>
                        </div>


                        {/* Mobile view tabs */}
                        <div
                            className="
                                sidebar-scroll
                                flex gap-1
                                overflow-x-auto
                                px-3 py-3
                                lg:hidden
                            "
                        >
                            {views.map((view) => {
                                const Icon = view.icon;

                                const active =
                                    view.key === activeView;

                                return (
                                    <button
                                        key={view.key}
                                        type="button"
                                        onClick={() =>
                                            apply({
                                                view:
                                                    view.key,
                                            })
                                        }
                                        className={`
                                            flex shrink-0
                                            items-center gap-1.5
                                            rounded-lg
                                            px-3 py-2
                                            text-xs
                                            font-semibold
                                            transition-all
                                            ${
                                                active
                                                    ? `
                                                        bg-white
                                                        text-brand-700
                                                        shadow-sm
                                                        ring-1
                                                        ring-slate-200
                                                    `
                                                    : `
                                                        text-slate-500
                                                        hover:bg-white
                                                        hover:text-slate-800
                                                    `
                                            }
                                        `}
                                    >
                                        <Icon className="h-3.5 w-3.5" />

                                        {view.label}
                                    </button>
                                );
                            })}
                        </div>


                        {/* Desktop views */}
                        <nav
                            className="
                                hidden space-y-1
                                px-2.5 py-3
                                lg:block
                            "
                            aria-label="Ticket views"
                        >
                            {views.map((view) => {
                                const Icon = view.icon;

                                const active =
                                    activeView === view.key;

                                return (
                                    <button
                                        key={view.key}
                                        type="button"
                                        onClick={() =>
                                            apply({
                                                view:
                                                    view.key,
                                            })
                                        }
                                        className={`
                                            group
                                            flex w-full
                                            items-center gap-2.5
                                            rounded-lg
                                            px-2.5 py-2
                                            text-left
                                            text-[13px]
                                            font-medium
                                            transition-all
                                            ${
                                                active
                                                    ? `
                                                        bg-white
                                                        text-brand-700
                                                        shadow-sm
                                                        ring-1
                                                        ring-slate-200/70
                                                    `
                                                    : `
                                                        text-slate-600
                                                        hover:bg-white
                                                        hover:text-slate-900
                                                    `
                                            }
                                        `}
                                    >
                                        <Icon
                                            className={`
                                                h-4 w-4
                                                shrink-0
                                                ${
                                                    active
                                                        ? 'text-brand-600'
                                                        : 'text-slate-400 group-hover:text-slate-600'
                                                }
                                            `}
                                            strokeWidth={1.8}
                                        />

                                        <span className="min-w-0 truncate">
                                            {view.label}
                                        </span>
                                    </button>
                                );
                            })}
                        </nav>

                    </aside>


                    {/* =========================
                        CONTENT
                    ========================== */}

                    <section className="flex min-w-0 flex-col">

                        {/* =========================
                            FILTERS
                        ========================== */}

                        <div
                            className="
                                border-b border-slate-200
                                bg-white
                                px-3 py-3
                                sm:px-4
                            "
                        >
                            <div
                                className="
                                    flex flex-col gap-2.5
                                    xl:flex-row
                                    xl:items-center
                                "
                            >

                                {/* Search */}
                                <form
                                    onSubmit={submitSearch}
                                    className="min-w-0 flex-1"
                                >
                                    <SearchInput
                                        value={search}
                                        onChange={setSearch}
                                        onClear={() => {
                                            setSearch('');

                                            apply({
                                                search: '',
                                            });
                                        }}
                                        placeholder="Search number, subject or customer..."
                                        className="w-full"
                                    />
                                </form>


                                {/* Dropdown filters */}
                                <div
                                    className="
                                        grid grid-cols-2 gap-2
                                        sm:grid-cols-3
                                        xl:flex
                                        xl:shrink-0
                                    "
                                >

                                    <Select
                                        value={
                                            filters.status ||
                                            ''
                                        }
                                        onChange={(event) =>
                                            apply({
                                                status:
                                                    event.target
                                                        .value,
                                            })
                                        }
                                        className="w-full xl:w-40"
                                    >
                                        <option value="">
                                            All statuses
                                        </option>

                                        {statuses.map(
                                            (status) => (
                                                <option
                                                    key={
                                                        status.id
                                                    }
                                                    value={
                                                        status.id
                                                    }
                                                >
                                                    {
                                                        status.name
                                                    }
                                                </option>
                                            ),
                                        )}
                                    </Select>


                                    <Select
                                        value={
                                            filters.priority ||
                                            ''
                                        }
                                        onChange={(event) =>
                                            apply({
                                                priority:
                                                    event.target
                                                        .value,
                                            })
                                        }
                                        className="w-full xl:w-36"
                                    >
                                        <option value="">
                                            All priorities
                                        </option>

                                        {[
                                            'low',
                                            'normal',
                                            'high',
                                            'urgent',
                                        ].map(
                                            (priority) => (
                                                <option
                                                    key={
                                                        priority
                                                    }
                                                    value={
                                                        priority
                                                    }
                                                >
                                                    {priority
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                        priority.slice(
                                                            1,
                                                        )}
                                                </option>
                                            ),
                                        )}
                                    </Select>


                                    <Select
                                        value={
                                            filters.team ||
                                            ''
                                        }
                                        onChange={(event) =>
                                            apply({
                                                team:
                                                    event.target
                                                        .value,
                                            })
                                        }
                                        className="col-span-2 w-full sm:col-span-1 xl:w-40"
                                    >
                                        <option value="">
                                            All teams
                                        </option>

                                        {teams.map(
                                            (team) => (
                                                <option
                                                    key={
                                                        team.id
                                                    }
                                                    value={
                                                        team.id
                                                    }
                                                >
                                                    {
                                                        team.name
                                                    }
                                                </option>
                                            ),
                                        )}
                                    </Select>

                                </div>
                            </div>
                        </div>


                        {/* =========================
                            LIST META
                        ========================== */}

                        <div
                            className="
                                flex items-center
                                justify-between
                                gap-3
                                border-b
                                border-slate-100
                                bg-slate-50/40
                                px-4 py-2.5
                            "
                        >

                            <p className="text-xs font-semibold text-slate-600">
                                {
                                    views.find(
                                        (view) =>
                                            view.key ===
                                            activeView,
                                    )?.label
                                }
                            </p>


                            <div
                                className="
                                    flex items-center
                                    gap-1.5
                                    text-xs
                                    text-slate-400
                                "
                            >
                                <SlidersHorizontal className="h-3.5 w-3.5" />

                                <span>
                                    {tickets.total ??
                                        tickets.data
                                            .length}{' '}
                                    tickets
                                </span>
                            </div>

                        </div>


                        {/* =========================
                            TICKET TABLE
                        ========================== */}

                        <div className="min-h-0 flex-1">

                            {tickets.data.length ? (
                                <div className="overflow-x-auto">

                                    <table className="min-w-[900px] w-full text-sm">

                                        <thead
                                            className="
                                                sticky top-0
                                                z-10
                                                border-b
                                                border-slate-200
                                                bg-slate-50/95
                                                backdrop-blur
                                            "
                                        >
                                            <tr>

                                                {[
                                                    'Ticket',
                                                    'Customer',
                                                    'Status',
                                                    'Priority',
                                                    'Owner',
                                                    'Updated',
                                                ].map(
                                                    (
                                                        heading,
                                                    ) => (
                                                        <th
                                                            key={
                                                                heading
                                                            }
                                                            className="
                                                                px-4 py-3
                                                                text-left
                                                                text-[10px]
                                                                font-semibold
                                                                uppercase
                                                                tracking-[0.08em]
                                                                text-slate-400
                                                            "
                                                        >
                                                            {
                                                                heading
                                                            }
                                                        </th>
                                                    ),
                                                )}

                                            </tr>
                                        </thead>


                                        <tbody className="divide-y divide-slate-100">

                                            {tickets.data.map(
                                                (ticket) => {
                                                    const owner =
                                                        ticket
                                                            .assignee
                                                            ?.name ||
                                                        ticket
                                                            .team
                                                            ?.name ||
                                                        'Unassigned';

                                                    const customer =
                                                        ticket
                                                            .contact
                                                            ?.display_name ||
                                                        'Unknown customer';

                                                    return (
                                                        <tr
                                                            key={
                                                                ticket.public_uuid
                                                            }
                                                            className="
                                                                group
                                                                transition-colors
                                                                hover:bg-slate-50/80
                                                            "
                                                        >

                                                            {/* Ticket */}
                                                            <td className="px-4 py-3.5">

                                                                <div className="max-w-[340px]">

                                                                    <Link
                                                                        href={route(
                                                                            'tenant.admin.tickets.show',
                                                                            ticket.public_uuid,
                                                                        )}
                                                                        className="
                                                                            inline-flex
                                                                            font-semibold
                                                                            text-brand-700
                                                                            transition-colors
                                                                            hover:text-brand-800
                                                                        "
                                                                    >
                                                                        {
                                                                            ticket.ticket_number
                                                                        }
                                                                    </Link>


                                                                    <Link
                                                                        href={route(
                                                                            'tenant.admin.tickets.show',
                                                                            ticket.public_uuid,
                                                                        )}
                                                                        className="
                                                                            mt-1
                                                                            block
                                                                            truncate
                                                                            text-sm
                                                                            font-medium
                                                                            text-slate-700
                                                                            transition-colors
                                                                            group-hover:text-slate-900
                                                                        "
                                                                    >
                                                                        {
                                                                            ticket.subject
                                                                        }
                                                                    </Link>

                                                                </div>

                                                            </td>


                                                            {/* Customer */}
                                                            <td className="px-4 py-3.5">

                                                                <div
                                                                    className="
                                                                        max-w-[180px]
                                                                        truncate
                                                                        font-medium
                                                                        text-slate-700
                                                                    "
                                                                >
                                                                    {
                                                                        customer
                                                                    }
                                                                </div>

                                                            </td>


                                                            {/* Status */}
                                                            <td className="px-4 py-3.5">

                                                                <Badge
                                                                    tone={getStatusTone(
                                                                        ticket
                                                                            .status
                                                                            ?.category,
                                                                    )}
                                                                >
                                                                    {ticket
                                                                        .status
                                                                        ?.name ||
                                                                        'Unknown'}
                                                                </Badge>

                                                            </td>


                                                            {/* Priority */}
                                                            <td className="px-4 py-3.5">

                                                                <Badge
                                                                    tone={
                                                                        priorityTone[
                                                                            ticket
                                                                                .priority
                                                                        ] ||
                                                                        'neutral'
                                                                    }
                                                                >
                                                                    {ticket.priority ||
                                                                        'normal'}
                                                                </Badge>

                                                            </td>


                                                            {/* Owner */}
                                                            <td className="px-4 py-3.5">

                                                                <div
                                                                    className={`
                                                                        max-w-[180px]
                                                                        truncate
                                                                        text-sm
                                                                        ${
                                                                            owner ===
                                                                            'Unassigned'
                                                                                ? 'text-slate-400'
                                                                                : 'font-medium text-slate-700'
                                                                        }
                                                                    `}
                                                                >
                                                                    {
                                                                        owner
                                                                    }
                                                                </div>

                                                            </td>


                                                            {/* Updated */}
                                                            <td className="whitespace-nowrap px-4 py-3.5">

                                                                <div className="text-xs font-medium text-slate-500">
                                                                    {formatDate(
                                                                        ticket.updated_at,
                                                                    )}
                                                                </div>

                                                            </td>

                                                        </tr>
                                                    );
                                                },
                                            )}

                                        </tbody>
                                    </table>

                                </div>
                            ) : (
                                <div
                                    className="
                                        flex
                                        min-h-[430px]
                                        items-center
                                        justify-center
                                        p-6
                                    "
                                >
                                    <EmptyState
                                        icon={
                                            TicketCheck
                                        }
                                        title="No tickets found"
                                        description="Create a ticket or link one from a conversation."
                                        className="max-w-md"
                                    />
                                </div>
                            )}

                        </div>


                        {/* =========================
                            PAGINATION
                        ========================== */}

                        {tickets.data.length > 0 && (
                            <div
                                className="
                                    border-t
                                    border-slate-200
                                    bg-slate-50/30
                                    px-3 py-3
                                    sm:px-4
                                "
                            >
                                <Pagination
                                    links={
                                        tickets.links
                                    }
                                />
                            </div>
                        )}

                    </section>
                </div>
            </div>

        </AuthenticatedLayout>
    );
}