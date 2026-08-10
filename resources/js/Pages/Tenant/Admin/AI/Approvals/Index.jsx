import AIShell from '@/Components/AI/AIShell';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import { router } from '@inertiajs/react';

export default function Approvals({ approvals, statusFilter, canDecide }) {
    const decide = (approval, action) => {
        const reason = window.prompt(`${action === 'approve' ? 'Approve' : 'Reject'} ${approval.action}. Optional reason:`);
        if (reason === null) return;
        router.post(route(`tenant.admin.ai.approvals.${action}`, approval.public_uuid), { reason }, { preserveScroll: true });
    };
    return <AIShell title="AI approvals" description="Review risk-gated tool actions. Arguments shown here are redacted; encrypted execution data is never exposed to the browser.">
        <div className="mb-4 flex flex-wrap gap-2">{['pending','approved','executed','rejected','failed','all'].map((status) => <Button key={status} size="sm" variant={statusFilter === status ? 'primary' : 'secondary'} onClick={() => router.get(route('tenant.admin.ai.approvals.index'), { status }, { preserveState: true })}>{status}</Button>)}</div>
        <div className="space-y-4">{approvals.map((approval) => <SectionCard key={approval.public_uuid} title={approval.action} description={`${approval.agent || 'Agent'} · ${approval.tool_key || 'connection action'}`} actions={<div className="flex gap-2"><Badge tone={['high','critical'].includes(approval.risk_level) ? 'danger' : 'warning'}>{approval.risk_level} risk</Badge><Badge tone={approval.status === 'executed' ? 'success' : approval.status === 'rejected' || approval.status === 'failed' ? 'danger' : 'neutral'}>{approval.status}</Badge></div>}>
            <pre className="overflow-auto rounded-md bg-slate-50 p-3 text-xs text-slate-600">{JSON.stringify(approval.arguments || {}, null, 2)}</pre><p className="mt-3 text-xs text-slate-400">Requested {new Date(approval.requested_at).toLocaleString()}{approval.expires_at ? ` · expires ${new Date(approval.expires_at).toLocaleString()}` : ''}</p>
            {canDecide && approval.status === 'pending' && <div className="mt-4 flex gap-2"><Button size="sm" onClick={() => decide(approval, 'approve')}>Approve and execute</Button><Button size="sm" variant="danger" onClick={() => decide(approval, 'reject')}>Reject</Button></div>}
        </SectionCard>)}{!approvals.length && <div className="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No approval requests match this filter.</div>}</div>
    </AIShell>;
}
