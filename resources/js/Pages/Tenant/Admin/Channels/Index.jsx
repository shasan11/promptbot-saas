import Pagination from '@/Components/Superadmin/Pagination';
import EngagementShell from '@/Components/Tenant/Engagement/EngagementShell';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import Select from '@/Components/UI/Select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, Mail, MessageCircle, MessagesSquare, Plus, SlidersHorizontal } from 'lucide-react';

const typeIcon = { email: Mail, web_chat: MessageCircle };

export default function Index({ channels, catalog = [], filters = {} }) {
    const permissions = usePage().props.auth?.permissions || [];
    const apply = (next) => router.get(route('tenant.admin.channels.index'), { ...filters, ...next }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <EngagementShell title="Channels" flush>
            <Head title="Channels" />
            <div className="border-b border-slate-200 px-3 py-3 sm:px-4">
                <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                    <div className="grid flex-1 grid-cols-2 gap-2 sm:flex">
                        <Select value={filters.type || ''} onChange={(event) => apply({ type: event.target.value })} className="w-full sm:w-44"><option value="">All channel types</option>{catalog.map((item) => <option key={item.type} value={item.type}>{item.label}</option>)}</Select>
                        <Select value={filters.status || ''} onChange={(event) => apply({ status: event.target.value })} className="w-full sm:w-40"><option value="">All statuses</option><option value="active">Active</option><option value="draft">Draft</option><option value="disabled">Disabled</option></Select>
                    </div>
                    {permissions.includes('channels.create') && <Button href={route('tenant.admin.channels.create')} variant="brand" size="sm" icon={Plus}>Add channel</Button>}
                </div>
            </div>
            <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/40 px-4 py-2.5"><div><p className="text-xs font-semibold text-slate-600">Communication channels</p><p className="mt-0.5 hidden text-[11px] text-slate-400 sm:block">Customer entry points and routing defaults</p></div><span className="flex items-center gap-1.5 text-xs text-slate-400"><SlidersHorizontal className="h-3.5 w-3.5" />{channels.total ?? channels.data.length} channels</span></div>

            <div className="min-h-0 flex-1 p-4 sm:p-5">
                {channels.data.length ? <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">{channels.data.map((channel) => { const Icon = typeIcon[channel.type] || MessagesSquare; return <Link key={channel.public_uuid} href={route('tenant.admin.channels.show', channel.public_uuid)} className="group rounded-lg border border-slate-200 bg-white p-4 shadow-soft transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-soft-lg"><div className="flex items-start justify-between"><span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><Icon className="h-4 w-4" /></span><Badge tone={channel.status === 'active' ? 'brand' : channel.status === 'disabled' ? 'danger' : 'neutral'}>{channel.status}</Badge></div><div className="mt-3 flex items-center gap-2"><h2 className="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900 group-hover:text-brand-700">{channel.name}</h2><ChevronRight className="h-4 w-4 text-slate-300 group-hover:text-brand-500" /></div><p className="mt-1 text-xs capitalize text-slate-500">{channel.type.replaceAll('_', ' ')}</p><p className="mt-3 truncate border-t border-slate-100 pt-3 text-[11px] text-slate-500">{channel.team?.name || 'No team'} · {channel.default_assignee?.name || 'No default assignee'}</p></Link>; })}</div> : <div className="flex min-h-[360px] items-center justify-center"><EmptyState icon={MessagesSquare} title="No channels configured" description="Add an email inbox or web chat widget to receive customer conversations." action={permissions.includes('channels.create') && <Button href={route('tenant.admin.channels.create')} variant="brand" icon={Plus}>Add channel</Button>} /></div>}

                <section className="mt-6 border-t border-slate-200 pt-5"><h2 className="text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Provider availability</h2><div className="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{catalog.map((item) => <div key={item.type} className="rounded-lg border border-slate-200 bg-slate-50/50 p-3"><div className="flex items-center justify-between gap-2"><span className="text-sm font-medium text-slate-800">{item.label}</span><Badge tone={item.available ? 'brand' : 'neutral'}>{item.available ? 'Available' : 'Provider needed'}</Badge></div><p className="mt-2 text-[11px] leading-5 text-slate-500">{item.available ? item.capabilities.join(' · ').replaceAll('_', ' ') : 'Extension interface included; provider not installed.'}</p></div>)}</div></section>
            </div>
            {channels.data.length > 0 && <div className="border-t border-slate-200 bg-slate-50/30 px-3 py-3 sm:px-4"><Pagination links={channels.links} /></div>}
        </EngagementShell>
    );
}
