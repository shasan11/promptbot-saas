import Pagination from '@/Components/Superadmin/Pagination';
import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import SearchInput from '@/Components/UI/SearchInput';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    CheckSquare,
    ChevronRight,
    CircleUserRound,
    Clock3,
    ListChecks,
    Plus,
    SlidersHorizontal,
    Users,
} from 'lucide-react';
import { useState } from 'react';

const views = [
    { key: 'mine', label: 'My tasks', icon: CircleUserRound },
    { key: 'all', label: 'All tasks', icon: ListChecks },
    { key: 'today', label: 'Due today', icon: Clock3 },
    { key: 'upcoming', label: 'Upcoming', icon: CalendarClock },
    { key: 'overdue', label: 'Overdue', icon: Clock3 },
    { key: 'completed', label: 'Completed', icon: CheckCircle2 },
];

const statusTone = {
    todo: 'neutral',
    in_progress: 'info',
    blocked: 'danger',
    completed: 'brand',
    cancelled: 'neutral',
};

const priorityTone = {
    urgent: 'danger',
    high: 'warning',
    normal: 'info',
    low: 'neutral',
};

function formatDueDate(date, completed = false) {
    if (!date) return { label: 'No due date', overdue: false };

    const value = new Date(date);
    const now = new Date();
    const overdue = value < now && !completed;
    const sameDay = value.toDateString() === now.toDateString();

    if (sameDay) {
        return {
            label: `Today, ${value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`,
            overdue,
        };
    }

    return {
        label: value.toLocaleDateString([], { month: 'short', day: 'numeric', year: value.getFullYear() !== now.getFullYear() ? 'numeric' : undefined }),
        overdue,
    };
}

export default function Index({ tasks, filters = {}, users = [] }) {
    const permissions = usePage().props.auth?.permissions || [];
    const [search, setSearch] = useState(filters.search || '');
    const activeView = filters.view || 'mine';

    const apply = (next = {}) => {
        router.get(
            route('tenant.admin.tasks.index'),
            { ...filters, search, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event) => {
        event.preventDefault();
        apply({ search: search.trim() });
    };

    return (
        <AuthenticatedLayout title="Tasks">
            <Head title="Tasks" />

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div className="grid min-h-[calc(100vh-8rem)] lg:grid-cols-[190px_minmax(0,1fr)]">
                    <aside className="border-b border-slate-200 bg-slate-50/70 lg:border-b-0 lg:border-r">
                        <div className="hidden px-4 pb-2 pt-5 lg:block">
                            <div className="flex items-center gap-2">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
                                    <CheckSquare className="h-4 w-4" />
                                </div>
                                <div>
                                    <h1 className="text-sm font-bold text-navy-900">Tasks</h1>
                                    <p className="text-[11px] text-slate-400">Team workload</p>
                                </div>
                            </div>
                        </div>

                        <div className="sidebar-scroll flex gap-1 overflow-x-auto px-3 py-3 lg:hidden">
                            {views.map((view) => {
                                const Icon = view.icon;
                                const active = activeView === view.key;

                                return (
                                    <button
                                        key={view.key}
                                        type="button"
                                        onClick={() => apply({ view: view.key })}
                                        className={`flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold transition-all ${active ? 'bg-white text-brand-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:bg-white hover:text-slate-800'}`}
                                    >
                                        <Icon className="h-3.5 w-3.5" />
                                        {view.label}
                                    </button>
                                );
                            })}
                        </div>

                        <nav className="hidden space-y-1 px-2.5 py-3 lg:block" aria-label="Task views">
                            {views.map((view) => {
                                const Icon = view.icon;
                                const active = activeView === view.key;

                                return (
                                    <button
                                        key={view.key}
                                        type="button"
                                        onClick={() => apply({ view: view.key })}
                                        className={`group flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-medium transition-all ${active ? 'bg-white text-brand-700 shadow-sm ring-1 ring-slate-200/70' : 'text-slate-600 hover:bg-white hover:text-slate-900'}`}
                                    >
                                        <Icon className={`h-4 w-4 shrink-0 ${active ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600'}`} strokeWidth={1.8} />
                                        <span className="flex-1">{view.label}</span>
                                        {active && <ChevronRight className="h-3.5 w-3.5 text-brand-400" />}
                                    </button>
                                );
                            })}
                        </nav>
                    </aside>

                    <section className="flex min-w-0 flex-col">
                        <div className="border-b border-slate-200 bg-white px-3 py-3 sm:px-4">
                            <div className="flex flex-col gap-2.5 xl:flex-row xl:items-center">
                                <form onSubmit={submitSearch} className="min-w-0 flex-1">
                                    <SearchInput
                                        value={search}
                                        onChange={setSearch}
                                        onClear={() => {
                                            setSearch('');
                                            apply({ search: '' });
                                        }}
                                        placeholder="Search tasks..."
                                        className="w-full"
                                    />
                                </form>

                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:flex xl:shrink-0">
                                    <Select value={filters.assignee || ''} onChange={(event) => apply({ assignee: event.target.value })} className="w-full xl:w-40">
                                        <option value="">All assignees</option>
                                        {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                                    </Select>
                                    <Select value={filters.status || ''} onChange={(event) => apply({ status: event.target.value })} className="w-full xl:w-36">
                                        <option value="">All statuses</option>
                                        <option value="todo">To do</option>
                                        <option value="in_progress">In progress</option>
                                        <option value="blocked">Blocked</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </Select>
                                    <Select value={filters.priority || ''} onChange={(event) => apply({ priority: event.target.value })} className="w-full xl:w-32">
                                        <option value="">All priorities</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="high">High</option>
                                        <option value="normal">Normal</option>
                                        <option value="low">Low</option>
                                    </Select>
                                </div>

                                {permissions.includes('tasks.create') && (
                                    <Button href={route('tenant.admin.tasks.create')} variant="brand" size="sm" icon={Plus} className="shrink-0">
                                        New task
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/40 px-4 py-2.5">
                            <p className="text-xs font-semibold text-slate-600">{views.find((view) => view.key === activeView)?.label}</p>
                            <div className="flex items-center gap-1.5 text-xs text-slate-400">
                                <SlidersHorizontal className="h-3.5 w-3.5" />
                                <span>{tasks.total ?? tasks.data.length} tasks</span>
                            </div>
                        </div>

                        <div className="min-h-0 flex-1">
                            {tasks.data.length ? (
                                <div className="divide-y divide-slate-100">
                                    {tasks.data.map((task) => {
                                        const completed = ['completed', 'cancelled'].includes(task.status);
                                        const due = formatDueDate(task.due_at, completed);
                                        const completedSubtasks = task.subtasks.filter((subtask) => subtask.status === 'completed').length;

                                        return (
                                            <Link
                                                key={task.public_uuid}
                                                href={route('tenant.admin.tasks.show', task.public_uuid)}
                                                className="group flex min-w-0 items-center gap-3 px-4 py-3.5 transition-colors hover:bg-slate-50/80"
                                            >
                                                <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${completed ? 'bg-brand-50 text-brand-600' : due.overdue ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-500'}`}>
                                                    {completed ? <CheckCircle2 className="h-4 w-4" /> : <CheckSquare className="h-4 w-4" />}
                                                </span>

                                                <div className="min-w-0 flex-1">
                                                    <div className="flex min-w-0 items-center gap-2">
                                                        <p className={`truncate text-sm font-semibold transition-colors group-hover:text-brand-700 ${completed ? 'text-slate-500 line-through' : 'text-slate-900'}`}>{task.title}</p>
                                                        <Badge tone={priorityTone[task.priority] || 'neutral'} className="hidden shrink-0 sm:inline-flex">{task.priority}</Badge>
                                                    </div>
                                                    <div className="mt-1 flex min-w-0 items-center gap-2 text-xs text-slate-500">
                                                        <span className="flex min-w-0 items-center gap-1.5 truncate">
                                                            {task.assignee ? <Avatar name={task.assignee.name} size="sm" className="!h-4 !w-4 !text-[8px]" /> : <Users className="h-3.5 w-3.5 text-slate-400" />}
                                                            <span className="truncate">{task.assignee?.name || 'Unassigned'}</span>
                                                        </span>
                                                        <span className="text-slate-300">•</span>
                                                        <span className="shrink-0">{completedSubtasks}/{task.subtasks.length} subtasks</span>
                                                    </div>
                                                </div>

                                                <div className="hidden shrink-0 text-right md:block">
                                                    <Badge tone={statusTone[task.status] || 'neutral'}>{task.status.replaceAll('_', ' ')}</Badge>
                                                    <p className={`mt-1.5 text-[11px] font-medium ${due.overdue ? 'text-rose-600' : 'text-slate-500'}`}>{due.overdue ? 'Overdue · ' : ''}{due.label}</p>
                                                </div>
                                                <ChevronRight className="h-4 w-4 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-brand-500" />
                                            </Link>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="flex min-h-[430px] items-center justify-center p-6">
                                    <EmptyState icon={CheckSquare} title="No tasks found" description="Create a task or adjust the selected view and filters." className="max-w-md" />
                                </div>
                            )}
                        </div>

                        {tasks.data.length > 0 && (
                            <div className="border-t border-slate-200 bg-slate-50/30 px-3 py-3 sm:px-4">
                                <Pagination links={tasks.links} />
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
