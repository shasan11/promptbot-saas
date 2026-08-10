import AIShell from '@/Components/AI/AIShell';
import StatCard from '@/Components/Superadmin/StatCard';
import Badge from '@/Components/UI/Badge';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import { Link } from '@inertiajs/react';
import { Bot, CheckCircle2, Clock3, Cpu, PlayCircle, Sparkles, Zap } from 'lucide-react';

export default function Overview({ metrics, recentRuns }) {
    const successRate = metrics.runs_30d ? Math.round((metrics.successful_runs_30d / metrics.runs_30d) * 100) : null;
    return (
        <AIShell title="AI overview" description="Operate tenant-scoped assistants, watch safety gates, and understand real usage without exposing provider secrets.">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Configured providers" value={metrics.providers} icon={Cpu} tone="blue" />
                <StatCard title="Active agents" value={metrics.active_agents} icon={Bot} tone="emerald" />
                <StatCard title="Runs in 30 days" value={metrics.runs_30d} icon={PlayCircle} tone="slate" />
                <StatCard title="Success rate" value={successRate === null ? 'No data yet' : `${successRate}%`} icon={CheckCircle2} tone="emerald" />
                <StatCard title="Pending approvals" value={metrics.pending_approvals} icon={Clock3} tone={metrics.pending_approvals ? 'amber' : 'slate'} />
                <StatCard title="Pending suggestions" value={metrics.pending_suggestions} icon={Sparkles} tone="blue" />
                <StatCard title="Tokens in 30 days" value={metrics.tokens_30d.toLocaleString()} icon={Zap} tone="slate" />
            </div>
            <SectionCard title="Recent runs" description="The latest recorded executions in this workspace." className="mt-6"
                actions={<Link className="text-sm font-semibold text-brand-700" href={route('tenant.admin.ai.providers.index')}>Manage providers</Link>}>
                {recentRuns.length ? <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="text-xs uppercase text-slate-400"><tr><th className="pb-3">Agent</th><th className="pb-3">Trigger</th><th className="pb-3">Status</th><th className="pb-3 text-right">Duration</th></tr></thead><tbody className="divide-y divide-slate-100">{recentRuns.map((run) => <tr key={run.public_uuid}><td className="py-3 font-medium text-slate-800">{run.agent || 'System'}</td><td className="py-3 text-slate-500">{run.trigger}</td><td className="py-3"><Badge tone={run.status === 'completed' ? 'success' : run.status === 'failed' ? 'danger' : 'neutral'}>{run.status}</Badge></td><td className="py-3 text-right text-slate-500">{run.duration_ms ? `${run.duration_ms} ms` : '—'}</td></tr>)}</tbody></table></div>
                    : <EmptyState icon={PlayCircle} title="No AI runs yet" description="Runs will appear after a configured agent or copilot action is executed." />}
            </SectionCard>
        </AIShell>
    );
}
