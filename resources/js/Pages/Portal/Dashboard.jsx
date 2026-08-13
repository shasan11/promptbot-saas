import Money from '@/Components/Portal/Money';
import Panel from '@/Components/Portal/Panel';
import StatusPill from '@/Components/Portal/StatusPill';
import PortalLayout from '@/Layouts/PortalLayout';
import { Link, usePage } from '@inertiajs/react';
import { Building2, CircleDollarSign, Headphones, ReceiptText } from 'lucide-react';

export default function Dashboard({ metrics, workspaces, recentInvoices, recentPayments, recentActivity }) {
    const { auth, portal } = usePage().props;
    const membership = portal?.membership;
    const canCreateWorkspace = ['owner', 'admin'].includes(membership?.role) || !!membership?.can_manage_services;
    const cards = [
        ['Active workspaces', metrics.activeWorkspaces, Building2],
        ['Monthly billing', <Money value={metrics.monthlyBilling} currency={portal.activeAccount.default_currency} />, CircleDollarSign],
        ['Outstanding balance', <Money value={metrics.outstandingBalance} currency={portal.activeAccount.default_currency} />, ReceiptText],
        ['Open support tickets', metrics.openSupportTickets, Headphones],
    ];
    return <PortalLayout title={`Welcome back, ${auth.user.name.split(' ')[0]}`} actions={canCreateWorkspace && <Link href={route('portal.workspaces.create')} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create workspace</Link>}>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{cards.map(([label, value, Icon]) => <div key={label} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><p className="text-sm font-medium text-slate-500">{label}</p><Icon className="h-5 w-5 text-indigo-500" /></div><div className="mt-3 text-2xl font-bold text-slate-950">{value}</div></div>)}</div>
        <div className="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
            <Panel title="Your workspaces" actions={<Link href={route('portal.workspaces.index')} className="text-sm font-semibold text-indigo-600">View all</Link>}>
                {workspaces.length === 0 ? <div className="py-10 text-center"><p className="font-semibold text-slate-800">No workspaces yet</p><p className="mt-1 text-sm text-slate-500">Create your first PromptBot workspace.</p></div> : <div className="grid gap-4 md:grid-cols-2">{workspaces.map((workspace) => <Link key={workspace.id} href={route('portal.workspaces.show', workspace.public_uuid)} className="rounded-lg border border-slate-200 p-4 hover:border-indigo-300"><div className="flex items-start justify-between gap-3"><div><h3 className="font-semibold text-slate-900">{workspace.company_name}</h3><p className="mt-1 text-sm text-slate-500">{workspace.domains?.[0]?.domain || `${workspace.slug}`}</p></div><StatusPill value={workspace.status} /></div><div className="mt-4 flex justify-between text-sm"><span className="text-slate-500">Plan</span><span className="font-medium text-slate-800">{workspace.subscriptions?.[0]?.plan?.name || workspace.plan?.name || 'Not assigned'}</span></div></Link>)}</div>}
            </Panel>
            <Panel title="Recent activity"><div className="space-y-4">{recentActivity.length ? recentActivity.map((activity) => <div key={activity.id} className="border-l-2 border-indigo-200 pl-3"><p className="text-sm font-medium text-slate-800">{activity.description || activity.event}</p><p className="mt-0.5 text-xs text-slate-500">{new Date(activity.created_at).toLocaleString()}</p></div>) : <p className="py-8 text-center text-sm text-slate-500">Account activity will appear here.</p>}</div></Panel>
        </div>
        <div className="mt-6 grid gap-6 lg:grid-cols-2">
            <Panel title="Recent invoices">{recentInvoices.length ? <div className="divide-y divide-slate-100">{recentInvoices.map((invoice) => <Link key={invoice.id} href={route('portal.billing.invoices.show', invoice.id)} className="flex items-center justify-between py-3"><div><p className="text-sm font-semibold text-slate-800">{invoice.number}</p><p className="text-xs text-slate-500">{invoice.issued_on || 'Draft'}</p></div><div className="text-right"><Money value={invoice.total} currency={invoice.currency} /><div className="mt-1"><StatusPill value={invoice.status} /></div></div></Link>)}</div> : <p className="py-8 text-center text-sm text-slate-500">Your invoices will appear here.</p>}</Panel>
            <Panel title="Recent payments">{recentPayments.length ? <div className="divide-y divide-slate-100">{recentPayments.map((payment) => <div key={payment.id} className="flex items-center justify-between py-3"><div><p className="text-sm font-semibold text-slate-800">{payment.provider_reference || payment.provider}</p><p className="text-xs text-slate-500">{new Date(payment.created_at).toLocaleDateString()}</p></div><div className="text-right"><Money value={payment.amount} currency={payment.currency} /><div className="mt-1"><StatusPill value={payment.status} /></div></div></div>)}</div> : <p className="py-8 text-center text-sm text-slate-500">Payments will appear after your first charge.</p>}</Panel>
        </div>
    </PortalLayout>;
}
