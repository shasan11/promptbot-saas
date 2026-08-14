import Pagination from '@/Components/Superadmin/Pagination';
import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import EmptyState from '@/Components/UI/EmptyState';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

import {
    Archive,
    AtSign,
    ChevronRight,
    CircleUserRound,
    Clock3,
    Inbox,
    MailOpen,
    MessageCircle,
    SlidersHorizontal,
    UserRoundCheck,
} from 'lucide-react';

import { useState } from 'react';


const views = [
    {
        key: 'all',
        label: 'All',
        icon: Inbox,
    },
    {
        key: 'mine',
        label: 'Mine',
        icon: CircleUserRound,
    },
    {
        key: 'unassigned',
        label: 'Unassigned',
        icon: UserRoundCheck,
    },
    {
        key: 'mentions',
        label: 'Mentions',
        icon: AtSign,
    },
    {
        key: 'snoozed',
        label: 'Snoozed',
        icon: Clock3,
    },
    {
        key: 'open',
        label: 'Open',
        icon: MailOpen,
    },
    {
        key: 'pending',
        label: 'Pending',
        icon: MessageCircle,
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

    const yesterday = new Date();
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
    conversations,
    filters,
    teams,
    channels,
}) {
    const [search, setSearch] = useState(filters.search || '');

    const activeView = filters.view || 'all';

    const apply = (next = {}) => {
        router.get(
            route('tenant.admin.inbox.index'),
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
        <AuthenticatedLayout title="Inbox">
            <Head title="Inbox" />

            <div
                className="
                    overflow-hidden
                    rounded-xl
                    border border-slate-200
                    bg-white
                    shadow-sm
                "
            >
                <div className="grid min-h-[calc(100vh-8rem)] lg:grid-cols-[190px_minmax(0,1fr)]">

                    {/* ==========================================
                        INBOX SIDEBAR
                    =========================================== */}

                    <aside
                        className="
                            border-b border-slate-200
                            bg-slate-50/70
                            lg:border-b-0
                            lg:border-r
                        "
                    >

                        {/* Desktop heading */}
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
                                    <Inbox className="h-4 w-4" />
                                </div>

                                <div>
                                    <h1 className="text-sm font-bold text-navy-900">
                                        Inbox
                                    </h1>

                                    <p className="text-[11px] text-slate-400">
                                        Conversations
                                    </p>
                                </div>
                            </div>
                        </div>


                        {/* Mobile horizontal views */}
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
                                    activeView === view.key;

                                return (
                                    <button
                                        key={view.key}
                                        type="button"
                                        onClick={() =>
                                            apply({
                                                view: view.key,
                                            })
                                        }
                                        className={`
                                            flex shrink-0
                                            items-center gap-1.5
                                            rounded-lg
                                            px-3 py-2
                                            text-xs font-semibold
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


                        {/* Desktop sidebar views */}
                        <nav
                            className="
                                hidden space-y-1
                                px-2.5 py-3
                                lg:block
                            "
                            aria-label="Inbox views"
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
                                                view: view.key,
                                            })
                                        }
                                        className={`
                                            group
                                            flex w-full
                                            items-center
                                            gap-2.5
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
                                                        : `
                                                            text-slate-400
                                                            group-hover:text-slate-600
                                                        `
                                                }
                                            `}
                                            strokeWidth={1.8}
                                        />

                                        <span className="flex-1">
                                            {view.label}
                                        </span>

                                        {active && (
                                            <ChevronRight className="h-3.5 w-3.5 text-brand-400" />
                                        )}
                                    </button>
                                );
                            })}
                        </nav>
                    </aside>


                    {/* ==========================================
                        CONVERSATION AREA
                    =========================================== */}

                    <section className="flex min-w-0 flex-col">

                        {/* ======================================
                            FILTER TOOLBAR
                        ======================================= */}

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
                                    md:flex-row
                                    md:items-center
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
                                        placeholder="Search conversations..."
                                        className="w-full"
                                    />
                                </form>


                                {/* Filters */}
                                <div
                                    className="
                                        grid grid-cols-2 gap-2
                                        md:flex
                                        md:shrink-0
                                    "
                                >
                                    <Select
                                        value={
                                            filters.team || ''
                                        }
                                        onChange={(event) =>
                                            apply({
                                                team:
                                                    event.target
                                                        .value,
                                            })
                                        }
                                        className="w-full md:w-40"
                                    >
                                        <option value="">
                                            All teams
                                        </option>

                                        {teams.map((team) => (
                                            <option
                                                key={team.id}
                                                value={team.id}
                                            >
                                                {team.name}
                                            </option>
                                        ))}
                                    </Select>


                                    <Select
                                        value={
                                            filters.channel ||
                                            ''
                                        }
                                        onChange={(event) =>
                                            apply({
                                                channel:
                                                    event.target
                                                        .value,
                                            })
                                        }
                                        className="w-full md:w-40"
                                    >
                                        <option value="">
                                            All channels
                                        </option>

                                        {channels.map(
                                            (channel) => (
                                                <option
                                                    key={
                                                        channel.id
                                                    }
                                                    value={
                                                        channel.id
                                                    }
                                                >
                                                    {
                                                        channel.name
                                                    }
                                                </option>
                                            ),
                                        )}
                                    </Select>
                                </div>
                            </div>
                        </div>


                        {/* ======================================
                            LIST HEADER
                        ======================================= */}

                        <div
                            className="
                                flex items-center justify-between
                                border-b border-slate-100
                                bg-slate-50/40
                                px-4 py-2.5
                            "
                        >
                            <div className="min-w-0">
                                <p className="text-xs font-semibold text-slate-600">
                                    {
                                        views.find(
                                            (view) =>
                                                view.key ===
                                                activeView,
                                        )?.label
                                    }{' '}
                                    conversations
                                </p>
                            </div>

                            <div className="flex items-center gap-1 text-xs text-slate-400">
                                <SlidersHorizontal className="h-3.5 w-3.5" />

                                <span className="hidden sm:inline">
                                    {conversations.total ??
                                        conversations.data
                                            .length}{' '}
                                    conversations
                                </span>
                            </div>
                        </div>


                        {/* ======================================
                            CONVERSATIONS
                        ======================================= */}

                        <div className="min-h-0 flex-1">

                            {conversations.data.length ? (
                                <div className="divide-y divide-slate-100">

                                    {conversations.data.map(
                                        (conversation) => {
                                            const unread =
                                                conversation.unread_count >
                                                0;

                                            const name =
                                                conversation
                                                    .contact
                                                    ?.display_name ||
                                                'Unknown contact';

                                            const preview =
                                                conversation
                                                    .latest_message
                                                    ?.body ||
                                                conversation.subject ||
                                                'No message preview';

                                            const assignee =
                                                conversation
                                                    .assignee
                                                    ?.name ||
                                                conversation.team
                                                    ?.name ||
                                                'Unassigned';

                                            const channel =
                                                conversation
                                                    .channel?.type
                                                    ?.replaceAll(
                                                        '_',
                                                        ' ',
                                                    ) ||
                                                'Unknown';

                                            return (
                                                <Link
                                                    key={
                                                        conversation.public_uuid
                                                    }
                                                    href={route(
                                                        'tenant.admin.inbox.show',
                                                        conversation.public_uuid,
                                                    )}
                                                    className={`
                                                        group
                                                        relative
                                                        block
                                                        px-3 py-3
                                                        transition-colors
                                                        sm:px-4
                                                        ${
                                                            unread
                                                                ? 'bg-brand-50/20'
                                                                : 'bg-white'
                                                        }
                                                        hover:bg-slate-50
                                                    `}
                                                >
                                                    {/* Unread indicator */}
                                                    {unread && (
                                                        <span
                                                            className="
                                                                absolute
                                                                bottom-0
                                                                left-0
                                                                top-0
                                                                w-[3px]
                                                                bg-brand-500
                                                            "
                                                        />
                                                    )}


                                                    <div
                                                        className="
                                                            grid
                                                            min-w-0
                                                            grid-cols-[auto_minmax(0,1fr)]
                                                            gap-3
                                                            sm:grid-cols-[auto_minmax(0,1fr)_auto]
                                                        "
                                                    >

                                                        {/* Avatar */}
                                                        <div className="pt-0.5">
                                                            <div className="relative">
                                                                <Avatar
                                                                    name={
                                                                        name
                                                                    }
                                                                    size="sm"
                                                                />

                                                                {unread && (
                                                                    <span
                                                                        className="
                                                                            absolute
                                                                            -right-0.5
                                                                            -top-0.5
                                                                            h-2.5
                                                                            w-2.5
                                                                            rounded-full
                                                                            border-2
                                                                            border-white
                                                                            bg-brand-600
                                                                        "
                                                                    />
                                                                )}
                                                            </div>
                                                        </div>


                                                        {/* Main content */}
                                                        <div className="min-w-0">

                                                            {/* Name */}
                                                            <div
                                                                className="
                                                                    flex
                                                                    min-w-0
                                                                    items-center
                                                                    gap-2
                                                                "
                                                            >
                                                                <span
                                                                    className={`
                                                                        min-w-0
                                                                        truncate
                                                                        text-sm
                                                                        ${
                                                                            unread
                                                                                ? 'font-bold text-slate-950'
                                                                                : 'font-semibold text-slate-800'
                                                                        }
                                                                    `}
                                                                >
                                                                    {name}
                                                                </span>


                                                                {conversation.priority &&
                                                                    conversation.priority !==
                                                                        'normal' && (
                                                                        <Badge
                                                                            tone={
                                                                                priorityTone[
                                                                                    conversation
                                                                                        .priority
                                                                                ]
                                                                            }
                                                                        >
                                                                            {
                                                                                conversation.priority
                                                                            }
                                                                        </Badge>
                                                                    )}
                                                            </div>


                                                            {/* Preview */}
                                                            <p
                                                                className={`
                                                                    mt-1
                                                                    truncate
                                                                    text-sm
                                                                    leading-5
                                                                    ${
                                                                        unread
                                                                            ? 'font-medium text-slate-700'
                                                                            : 'text-slate-500'
                                                                    }
                                                                `}
                                                            >
                                                                {
                                                                    preview
                                                                }
                                                            </p>


                                                            {/* Bottom metadata */}
                                                            <div
                                                                className="
                                                                    mt-2
                                                                    flex
                                                                    min-w-0
                                                                    flex-wrap
                                                                    items-center
                                                                    gap-1.5
                                                                "
                                                            >
                                                                {/* Mobile channel */}
                                                                <span
                                                                    className="
                                                                        inline-flex
                                                                        items-center
                                                                        rounded-md
                                                                        bg-slate-100
                                                                        px-1.5
                                                                        py-0.5
                                                                        text-[10px]
                                                                        font-medium
                                                                        capitalize
                                                                        text-slate-500
                                                                        sm:hidden
                                                                    "
                                                                >
                                                                    {
                                                                        channel
                                                                    }
                                                                </span>


                                                                {conversation.tags?.map(
                                                                    (
                                                                        tag,
                                                                    ) => (
                                                                        <Badge
                                                                            key={
                                                                                tag.public_uuid
                                                                            }
                                                                        >
                                                                            {
                                                                                tag.name
                                                                            }
                                                                        </Badge>
                                                                    ),
                                                                )}


                                                                {/* Mobile assignee */}
                                                                <span
                                                                    className="
                                                                        truncate
                                                                        text-[11px]
                                                                        text-slate-400
                                                                        sm:hidden
                                                                    "
                                                                >
                                                                    {
                                                                        assignee
                                                                    }
                                                                </span>
                                                            </div>
                                                        </div>


                                                        {/* Right metadata */}
                                                        <div
                                                            className="
                                                                hidden
                                                                min-w-[120px]
                                                                shrink-0
                                                                flex-col
                                                                items-end
                                                                text-right
                                                                sm:flex
                                                            "
                                                        >
                                                            <p
                                                                className={`
                                                                    text-xs
                                                                    ${
                                                                        unread
                                                                            ? 'font-semibold text-slate-700'
                                                                            : 'text-slate-400'
                                                                    }
                                                                `}
                                                            >
                                                                {formatDate(
                                                                    conversation.last_message_at,
                                                                )}
                                                            </p>


                                                            <span
                                                                className="
                                                                    mt-2
                                                                    inline-flex
                                                                    max-w-[140px]
                                                                    items-center
                                                                    rounded-md
                                                                    bg-slate-100
                                                                    px-2
                                                                    py-1
                                                                    text-[10px]
                                                                    font-medium
                                                                    capitalize
                                                                    text-slate-500
                                                                "
                                                            >
                                                                {
                                                                    channel
                                                                }
                                                            </span>


                                                            <p
                                                                className="
                                                                    mt-1.5
                                                                    max-w-[140px]
                                                                    truncate
                                                                    text-[11px]
                                                                    text-slate-400
                                                                "
                                                            >
                                                                {
                                                                    assignee
                                                                }
                                                            </p>
                                                        </div>

                                                    </div>


                                                    {/* Mobile date */}
                                                    <span
                                                        className="
                                                            absolute
                                                            right-3
                                                            top-3
                                                            text-[10px]
                                                            text-slate-400
                                                            sm:hidden
                                                        "
                                                    >
                                                        {formatDate(
                                                            conversation.last_message_at,
                                                        )}
                                                    </span>

                                                </Link>
                                            );
                                        },
                                    )}

                                </div>
                            ) : (
                                <div
                                    className="
                                        flex min-h-[440px]
                                        items-center
                                        justify-center
                                        p-6
                                    "
                                >
                                    <EmptyState
                                        icon={Inbox}
                                        title="No conversations here"
                                        description="New customer messages will appear in this view."
                                        className="max-w-md"
                                    />
                                </div>
                            )}

                        </div>


                        {/* ======================================
                            PAGINATION
                        ======================================= */}

                        {conversations.data.length > 0 && (
                            <div
                                className="
                                    border-t border-slate-200
                                    bg-slate-50/30
                                    px-3 py-3
                                    sm:px-4
                                "
                            >
                                <Pagination
                                    links={
                                        conversations.links
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