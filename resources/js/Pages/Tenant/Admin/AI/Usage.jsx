import AIShell from '@/Components/AI/AIShell';
import StatCard from '@/Components/Superadmin/StatCard';
import { SectionCard } from '@/Components/UI/Card';
import { Coins, Gauge, MessageSquare, Zap } from 'lucide-react';

const money = (value, currency) => new Intl.NumberFormat(undefined, {
    style: 'currency', currency, minimumFractionDigits: 4, maximumFractionDigits: 8,
}).format(Number(value));

export default function Usage({ totals, costTotals, byProvider, daily }) {
    const totalTokens = Number(totals.input_tokens) + Number(totals.output_tokens);
    const max = Math.max(1, ...daily.map((row) => Number(row.tokens)));
    const costValue = !costTotals.length ? 'Price unavailable' : costTotals.length === 1
        ? money(costTotals[0].cost, costTotals[0].currency) : `${costTotals.length} currencies`;

    return <AIShell title="AI usage" description="Actual provider-reported token usage for the last 30 days. Cost stays blank when no verified model price is configured.">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard title="Requests" value={Number(totals.runs).toLocaleString()} icon={MessageSquare} tone="blue" />
            <StatCard title="Input tokens" value={Number(totals.input_tokens).toLocaleString()} icon={Gauge} tone="slate" />
            <StatCard title="Output tokens" value={Number(totals.output_tokens).toLocaleString()} icon={Zap} tone="emerald" />
            <StatCard title="Estimated cost" value={costValue} icon={Coins} tone="slate" />
        </div>
        <SectionCard className="mt-6" title="Daily token usage">
            <div className="flex h-44 items-end gap-1">{daily.map((row) => <div key={row.day} className="group flex min-w-0 flex-1 flex-col items-center justify-end"><div title={`${row.day}: ${Number(row.tokens).toLocaleString()} tokens`} className="w-full rounded-t bg-brand-500/80" style={{ height: `${Math.max(3, Number(row.tokens) / max * 150)}px` }} /><span className="mt-1 hidden text-[9px] text-slate-400 xl:block">{row.day.slice(5)}</span></div>)}</div>
            {!daily.length && <p className="text-sm text-slate-500">No usage recorded yet.</p>}
        </SectionCard>
        <SectionCard className="mt-6" title="By provider and model">
            <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="text-xs uppercase text-slate-400"><tr><th className="pb-3">Provider</th><th className="pb-3">Model</th><th className="pb-3 text-right">Requests</th><th className="pb-3 text-right">Tokens</th><th className="pb-3 text-right">Cost</th></tr></thead><tbody className="divide-y divide-slate-100">{byProvider.map((row, index) => <tr key={`${row.provider}-${row.model}-${row.currency || 'unknown'}-${index}`}><td className="py-3 font-medium">{row.provider}</td><td className="py-3 text-slate-500">{row.model}</td><td className="py-3 text-right">{row.requests}</td><td className="py-3 text-right">{Number(row.tokens).toLocaleString()}</td><td className="py-3 text-right">{row.cost === null || !row.currency ? '—' : money(row.cost, row.currency)}</td></tr>)}</tbody></table></div>
            <p className="mt-3 text-xs text-slate-400">Total: {totalTokens.toLocaleString()} tokens{costTotals.length > 1 ? ` · Costs: ${costTotals.map((row) => money(row.cost, row.currency)).join(', ')}` : ''}</p>
        </SectionCard>
    </AIShell>;
}
