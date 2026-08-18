import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BellRing,
    CheckCircle2,
    CheckSquare,
    Circle,
    Inbox,
    MessageSquareText,
    Plus,
    Settings,
    TicketCheck,
    UserPlus,
    Users,
    Zap,
} from 'lucide-react';

const statusTones = { open: 'brand', pending: 'warning', waiting_on_customer: 'info', closed: 'neutral', resolved: 'neutral' };
const priorityTones = { urgent: 'danger', high: 'warning', normal: 'info', low: 'neutral' };

function MetricCard({ label, value, detail, icon: Icon, tone = 'brand', href }) {
    const tones = {
        brand: 'bg-brand-50 text-brand-700 ring-brand-100',
        blue: 'bg-blue-50 text-blue-700 ring-blue-100',
        amber: 'bg-amber-50 text-amber-700 ring-amber-100',
        violet: 'bg-violet-50 text-violet-700 ring-violet-100',
    };
    const content = (
        <div className="group relative overflow-hidden rounded-lg border border-slate-200 bg-white p-4 shadow-soft transition duration-200 hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-soft-lg">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">{label}</p>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-slate-950">{value}</p>
                    <p className="mt-1 text-xs text-slate-500">{detail}</p>
                </div>
                <span className={`flex h-9 w-9 items-center justify-center rounded-md ring-1 ${tones[tone]}`}><Icon className="h-[18px] w-[18px]" /></span>
            </div>
            <div className="absolute inset-x-0 bottom-0 h-0.5 origin-left scale-x-0 bg-brand-500 transition-transform group-hover:scale-x-100" />
        </div>
    );

    return href ? <Link href={href}>{content}</Link> : content;
}

function ActivityChart({ activity = [] }) {
    const maximum = Math.max(1, ...activity.flatMap((day) => [day.conversations, day.tickets]));

    return (
        <section className="rounded-lg border border-slate-200 bg-white shadow-soft">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 className="text-sm font-semibold text-slate-900">Support volume</h2>
                    <p className="mt-0.5 text-xs text-slate-500">New conversations and tickets over the last 7 days</p>
                </div>
                <div className="flex items-center gap-4 text-[11px] font-medium text-slate-500">
                    <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-brand-500" /> Conversations</span>
                    <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-navy-700" /> Tickets</span>
                </div>
            </div>
            <div className="px-5 pb-4 pt-5">
                <div className="flex h-44 items-end justify-between gap-2 border-b border-slate-200 bg-[linear-gradient(to_bottom,transparent_24%,rgb(226_232_240/.65)_25%,transparent_26%,transparent_49%,rgb(226_232_240/.65)_50%,transparent_51%,transparent_74%,rgb(226_232_240/.65)_75%,transparent_76%)]">
                    {activity.map((day) => (
                        <div key={day.date} className="group flex h-full flex-1 items-end justify-center gap-1">
                            <div title={`${day.conversations} conversations`} className="w-full max-w-5 rounded-t bg-brand-400 transition-all duration-500 group-hover:bg-brand-500" style={{ height: `${Math.max(day.conversations ? 8 : 2, (day.conversations / maximum) * 92)}%` }} />
                            <div title={`${day.tickets} tickets`} className="w-full max-w-5 rounded-t bg-navy-700 transition-all duration-500 group-hover:bg-navy-800" style={{ height: `${Math.max(day.tickets ? 8 : 2, (day.tickets / maximum) * 92)}%` }} />
                        </div>
                    ))}
                </div>
                <div className="mt-2 flex justify-between gap-2">{activity.map((day) => <span key={day.date} className="flex-1 text-center text-[10px] font-medium text-slate-400">{day.label}</span>)}</div>
            </div>
        </section>
    );
}

function RecentConversations({ conversations = [] }) {
    return (
        <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div><h2 className="text-sm font-semibold text-slate-900">Recent conversations</h2><p className="mt-0.5 text-xs text-slate-500">The latest customer activity across channels</p></div>
                <Link href={route('tenant.admin.inbox.index')} className="flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">Open inbox <ArrowRight className="h-3.5 w-3.5" /></Link>
            </div>
            {conversations.length ? (
                <div className="divide-y divide-slate-100">
                    {conversations.map((conversation) => (
                        <Link key={conversation.public_uuid} href={route('tenant.admin.inbox.show', conversation.public_uuid)} className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-slate-50">
                            <Avatar name={conversation.contact?.display_name || 'Customer'} size="sm" />
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2"><p className="truncate text-sm font-semibold text-slate-800">{conversation.contact?.display_name || 'Unknown customer'}</p>{conversation.unread_count > 0 && <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" />}</div>
                                <p className="mt-0.5 truncate text-xs text-slate-500">{conversation.subject || 'Customer conversation'} · {conversation.channel?.name || 'Support'}</p>
                            </div>
                            <div className="hidden items-center gap-2 sm:flex"><Badge tone={priorityTones[conversation.priority] || 'neutral'}>{conversation.priority}</Badge><Badge tone={statusTones[conversation.status] || 'neutral'}>{conversation.status.replaceAll('_', ' ')}</Badge></div>
                        </Link>
                    ))}
                </div>
            ) : <div className="px-5 py-10 text-center"><MessageSquareText className="mx-auto h-7 w-7 text-slate-300" /><p className="mt-2 text-sm font-medium text-slate-700">No conversations yet</p><p className="mt-1 text-xs text-slate-500">New customer messages will appear here.</p></div>}
        </section>
    );
}

function PriorityTasks({ tasks = [] }) {
    return (
        <section className="rounded-lg border border-slate-200 bg-white shadow-soft">
            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 className="text-sm font-semibold text-slate-900">Work queue</h2><p className="mt-0.5 text-xs text-slate-500">Next tasks by due date</p></div><Link href={route('tenant.admin.tasks.index')} className="text-xs font-semibold text-brand-700">View all</Link></div>
            <div className="p-2">
                {tasks.length ? tasks.map((task) => {
                    const overdue = task.due_at && new Date(task.due_at) < new Date();
                    return <Link key={task.public_uuid} href={route('tenant.admin.tasks.show', task.public_uuid)} className="flex items-start gap-3 rounded-md px-3 py-2.5 hover:bg-slate-50"><span className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full ${overdue ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-500'}`}><CheckSquare className="h-3.5 w-3.5" /></span><span className="min-w-0 flex-1"><span className="block truncate text-sm font-medium text-slate-800">{task.title}</span><span className={`mt-0.5 block text-[11px] ${overdue ? 'font-semibold text-rose-600' : 'text-slate-500'}`}>{task.due_at ? `${overdue ? 'Overdue · ' : ''}${new Date(task.due_at).toLocaleDateString([], { month: 'short', day: 'numeric' })}` : 'No due date'} · {task.assignee?.name || 'Unassigned'}</span></span><Badge tone={priorityTones[task.priority] || 'neutral'}>{task.priority}</Badge></Link>;
                }) : <p className="px-3 py-8 text-center text-sm text-slate-500">Your work queue is clear.</p>}
            </div>
        </section>
    );
}

export default function Dashboard({ tenant, stats = {}, activity = [], recentConversations = [], priorityTasks = [], recentUsers = [] }) {
    const { auth } = usePage().props;
    const permissions = auth?.permissions || [];
    const user = auth?.user;
    const can = (permission) => permissions.includes(permission);
    const hasSettingsConfigured = (stats.settings ?? 0) > 0;
    const checklist = [
        { label: 'Workspace provisioned', done: true },
        { label: 'Company settings configured', done: hasSettingsConfigured, href: can('workspace.view') ? route('tenant.admin.administration.workspace.edit', 'general') : null },
        { label: 'Support channel connected', done: (stats.activeChannels ?? 0) > 0, href: can('channels.view') ? route('tenant.admin.channels.index') : null },
        { label: 'Team members invited', done: (stats.users ?? 0) > 1, href: can('invitations.create') ? route('tenant.admin.administration.invitations.create') : null },
    ];
    const completed = checklist.filter((item) => item.done).length;
    const setupPercent = Math.round((completed / checklist.length) * 100);

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />

            <section className="relative mb-4 overflow-hidden rounded-xl bg-navy-900 px-5 py-5 text-white shadow-soft-lg sm:px-6">
                <div className="pointer-events-none absolute inset-0 opacity-[0.12] [background-image:linear-gradient(rgba(255,255,255,.45)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.45)_1px,transparent_1px)] [background-size:32px_32px]" />
                <div className="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-brand-500/30 blur-3xl" />
                <div className="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                    <div><div className="mb-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-300"><span className="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-400" /> Workspace overview</div><h1 className="text-xl font-bold tracking-tight sm:text-2xl">Good to see you, {user?.name?.split(' ')[0] || 'there'}.</h1><p className="mt-1 max-w-xl text-sm text-slate-300">Here’s what needs attention across {tenant.companyName} today.</p></div>
                    <div className="flex flex-wrap gap-2">{can('inbox.view') && <Button href={route('tenant.admin.inbox.index')} variant="brand" size="sm" icon={Inbox}>Open inbox</Button>}{can('tickets.create') && <Button href={route('tenant.admin.tickets.create')} variant="secondary" size="sm" icon={Plus}>New ticket</Button>}</div>
                </div>
            </section>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {can('inbox.view') && <MetricCard label="Open conversations" value={stats.openConversations ?? 0} detail={`${stats.unreadConversations ?? 0} with unread messages`} icon={MessageSquareText} href={route('tenant.admin.inbox.index')} />}
                {can('tickets.view') && <MetricCard label="Unresolved tickets" value={stats.unresolvedTickets ?? 0} detail="Across all support queues" icon={TicketCheck} tone="blue" href={route('tenant.admin.tickets.index')} />}
                {can('tasks.view') && <MetricCard label="Open tasks" value={stats.openTasks ?? 0} detail={`${stats.overdueTasks ?? 0} currently overdue`} icon={CheckSquare} tone="amber" href={route('tenant.admin.tasks.index')} />}
                {can('customers.view') && <MetricCard label="Contacts" value={stats.contacts ?? 0} detail={`${stats.activeChannels ?? 0} active support channels`} icon={Users} tone="violet" href={route('tenant.admin.customers.contacts.index')} />}
            </div>

            <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(19rem,.75fr)]">
                <div className="space-y-4">
                    <ActivityChart activity={activity} />
                    {can('inbox.view') && <RecentConversations conversations={recentConversations} />}
                </div>
                <div className="space-y-4">
                    {can('tasks.view') && <PriorityTasks tasks={priorityTasks} />}

                    <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                        <div className="flex items-center justify-between"><div><h2 className="text-sm font-semibold text-slate-900">Workspace setup</h2><p className="mt-0.5 text-xs text-slate-500">{completed} of {checklist.length} completed</p></div><span className="text-lg font-bold text-brand-700">{setupPercent}%</span></div>
                        <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full bg-brand-500 transition-all duration-700" style={{ width: `${setupPercent}%` }} /></div>
                        <ul className="mt-4 space-y-2.5">{checklist.map((item) => <li key={item.label}>{item.href && !item.done ? <Link href={item.href} className="flex items-center gap-2 text-xs font-medium text-slate-600 hover:text-brand-700"><Circle className="h-3.5 w-3.5 text-slate-300" />{item.label}</Link> : <span className="flex items-center gap-2 text-xs text-slate-500"><CheckCircle2 className="h-3.5 w-3.5 text-brand-600" />{item.label}</span>}</li>)}</ul>
                    </section>

                    <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                        <h2 className="text-sm font-semibold text-slate-900">Quick actions</h2>
                        <div className="mt-3 grid grid-cols-2 gap-2">
                            {can('customers.create') && <Link href={route('tenant.admin.customers.contacts.create')} className="rounded-md border border-slate-200 p-3 text-xs font-semibold text-slate-700 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700"><UserPlus className="mb-2 h-4 w-4" />Add contact</Link>}
                            {can('channels.create') && <Link href={route('tenant.admin.channels.create')} className="rounded-md border border-slate-200 p-3 text-xs font-semibold text-slate-700 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700"><Zap className="mb-2 h-4 w-4" />Add channel</Link>}
                            <Link href={route('tenant.admin.notifications.index')} className="rounded-md border border-slate-200 p-3 text-xs font-semibold text-slate-700 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700"><BellRing className="mb-2 h-4 w-4" />Notifications</Link>
                            {can('workspace.view') && <Link href={route('tenant.admin.administration.workspace.edit', 'general')} className="rounded-md border border-slate-200 p-3 text-xs font-semibold text-slate-700 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700"><Settings className="mb-2 h-4 w-4" />Settings</Link>}
                        </div>
                    </section>

                    {can('users.view') && recentUsers.length > 0 && <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft"><div className="flex items-center justify-between"><h2 className="text-sm font-semibold text-slate-900">Team</h2><Link href={route('tenant.admin.administration.users.index')} className="text-xs font-semibold text-brand-700">Manage</Link></div><div className="mt-3 flex -space-x-2">{recentUsers.slice(0, 6).map((member) => <Avatar key={member.id} name={member.name} size="sm" className="ring-2 ring-white" />)}<span className="ml-3 self-center pl-2 text-xs text-slate-500">{stats.users} member{stats.users === 1 ? '' : 's'}</span></div></section>}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
