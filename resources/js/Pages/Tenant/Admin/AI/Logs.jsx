import AIShell from '@/Components/AI/AIShell';
import Badge from '@/Components/UI/Badge';
import { SectionCard } from '@/Components/UI/Card';
import Select from '@/Components/UI/Select';
import { router } from '@inertiajs/react';

export default function Logs({ runs, filters }) {
    const filter = (key, value) => router.get(route('tenant.admin.ai.logs.index'), { ...filters, [key]: value || undefined }, { preserveState: true });
    return <AIShell title="AI run logs" description="Operational metadata and safe errors. Raw user inputs, provider credentials, and hidden prompts are not displayed.">
        <div className="mb-4 grid max-w-xl gap-3 sm:grid-cols-2"><Select value={filters.status || ''} onChange={(e) => filter('status', e.target.value)}><option value="">All statuses</option>{['queued','running','completed','failed','timed_out','rate_limited'].map((status) => <option key={status}>{status}</option>)}</Select><Select value={filters.feature || ''} onChange={(e) => filter('feature', e.target.value)}><option value="">All features</option>{['playground','inbox_copilot','evaluation','automation'].map((feature) => <option key={feature}>{feature}</option>)}</Select></div>
        <SectionCard><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="text-xs uppercase text-slate-400"><tr><th className="pb-3">Time / trace</th><th className="pb-3">Agent</th><th className="pb-3">Operation</th><th className="pb-3">Status</th><th className="pb-3 text-right">Tokens</th><th className="pb-3 text-right">Latency</th></tr></thead><tbody className="divide-y divide-slate-100">{runs.map((run) => <tr key={run.public_uuid}><td className="py-3"><p>{new Date(run.created_at).toLocaleString()}</p><p className="font-mono text-[10px] text-slate-400">{run.trace_id}</p></td><td className="py-3">{run.agent || 'System'}<p className="text-xs text-slate-400">{run.provider}</p></td><td className="py-3">{run.feature}<p className="text-xs text-slate-400">{run.operation}</p></td><td className="py-3"><Badge tone={run.status === 'completed' ? 'success' : run.status === 'failed' ? 'danger' : 'neutral'}>{run.status}</Badge>{run.error_message && <p className="mt-1 max-w-xs text-xs text-rose-600">{run.error_message}</p>}</td><td className="py-3 text-right">{run.tokens?.toLocaleString?.() || '—'}</td><td className="py-3 text-right">{run.latency_ms ? `${run.latency_ms} ms` : '—'}</td></tr>)}</tbody></table></div></SectionCard>
    </AIShell>;
}
