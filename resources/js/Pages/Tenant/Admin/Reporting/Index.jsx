import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';

function Breakdown({ title, rows }) {
    const max = Math.max(1, ...rows.map((row) => row.total));
    return <section className="rounded-lg border bg-white p-5"><h2 className="font-semibold">{title}</h2><div className="mt-4 space-y-3">{rows.map((row) => <div key={row.label}><div className="flex justify-between text-sm"><span>{row.label}</span><Badge tone="neutral">{row.total}</Badge></div><div className="mt-1 h-2 rounded bg-slate-100"><div className="h-2 rounded bg-brand-500" style={{ width: `${row.total / max * 100}%` }} /></div></div>)}</div></section>;
}

export default function Index({ filters, metrics, byChannel, byStatus, agents, aiInsight, aiEnabled }) {
    const cards = [['Conversations', metrics.conversations], ['Tickets', metrics.tickets], ['Resolved conversations', metrics.resolved_conversations], ['Resolved tickets', metrics.resolved_tickets], ['First response', `${metrics.first_response_minutes.toFixed(1)} min`], ['CSAT', metrics.csat.toFixed(2)], ['SLA breaches', metrics.sla_breaches]];
    return <AuthenticatedLayout title="Reports" header={<div className="flex flex-wrap items-end justify-between gap-3"><div><h1 className="text-xl font-bold">Reporting</h1><p className="text-sm text-slate-500">Operational metrics from first-party support data.</p></div><div className="flex gap-2">{aiEnabled && <Button variant="secondary" icon={Sparkles} onClick={() => router.post(route('tenant.admin.reports.ai-insight'), { from: String(filters.from).slice(0, 10), to: String(filters.to).slice(0, 10) }, { preserveScroll: true })}>Explain metrics</Button>}<a href={route('tenant.admin.reports.export', filters)}><Button variant="brand">Export CSV</Button></a></div></div>}>
        <Head title="Reports" />
        <form className="mb-5 flex flex-wrap gap-3" onSubmit={(event) => { event.preventDefault(); router.get(route('tenant.admin.reports.index'), Object.fromEntries(new FormData(event.currentTarget)), { preserveState: true }); }}><input type="date" name="from" defaultValue={String(filters.from).slice(0, 10)} className="rounded border px-3 py-2 text-sm" /><input type="date" name="to" defaultValue={String(filters.to).slice(0, 10)} className="rounded border px-3 py-2 text-sm" /><Button type="submit">Apply</Button></form>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{cards.map(([key, value]) => <article key={key} className="rounded-lg border bg-white p-4"><p className="text-xs uppercase text-slate-400">{key}</p><p className="mt-1 text-2xl font-bold">{value}</p></article>)}</div>
        {aiInsight && <SectionCard className="mt-6" title="AI observations" description="Generated only from the aggregated metrics above; possible causes are not treated as facts."><p className="whitespace-pre-wrap text-sm leading-6 text-slate-700">{aiInsight.text}</p><p className="mt-3 text-xs text-slate-400">Generated {new Date(aiInsight.created_at).toLocaleString()}</p></SectionCard>}
        <div className="mt-6 grid gap-5 lg:grid-cols-3"><Breakdown title="By channel" rows={byChannel} /><Breakdown title="By status" rows={byStatus} /><Breakdown title="Agent workload" rows={agents.map((agent) => ({ label: agent.name, total: agent.assigned }))} /></div>
    </AuthenticatedLayout>;
}
