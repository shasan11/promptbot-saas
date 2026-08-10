import AIShell from '@/Components/AI/AIShell';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import Input from '@/Components/UI/Input';
import Switch from '@/Components/UI/Switch';
import { useForm } from '@inertiajs/react';

export default function Settings({ settings, platform }) {
    const form = useForm(settings);
    const save = (event) => { event.preventDefault(); form.put(route('tenant.admin.ai.settings.update'), { preserveScroll: true }); };
    return (
        <AIShell title="AI settings" description="Workspace-level safety, cost, and retention controls. Platform policy always remains the upper bound.">
            <form onSubmit={save} className="space-y-6">
                <SectionCard title="Runtime and review">
                    <div className="space-y-5">
                        <Switch checked={form.data.enabled} onChange={(value) => form.setData('enabled', value)} disabled={!platform.enabled} label="Enable AI for this workspace" description={platform.enabled ? 'Allows configured agents and copilots to run.' : 'Disabled by the platform operator.'} />
                        <Switch checked={form.data.human_review_required} onChange={(value) => form.setData('human_review_required', value)} label="Require human review" description="Keep generated replies and actions as suggestions by default." />
                        <Switch checked={form.data.require_grounding} onChange={(value) => form.setData('require_grounding', value)} label="Require knowledge grounding" description="Factual answers must use accessible tenant knowledge or clearly state insufficient information." />
                        <Switch checked={form.data.require_citations} onChange={(value) => form.setData('require_citations', value)} label="Require citations" description="Grounded answers include source lineage when available." />
                        <Switch checked={form.data.allow_private_provider_endpoints} onChange={(value) => form.setData('allow_private_provider_endpoints', value)} disabled={!platform.private_provider_endpoints_enabled} label="Allow private Ollama endpoints" description="Available only when explicitly enabled by the platform operator." />
                        <Switch checked={form.data.background_inbox_analysis} onChange={(value) => form.setData('background_inbox_analysis', value)} label="Background inbox insights" description="Queue non-mutating summary and classification suggestions after inbound messages." />
                        <Switch checked={form.data.autonomous_replies_enabled} onChange={(value) => form.setData('autonomous_replies_enabled', value)} disabled={!platform.autonomous_replies_enabled || !platform.autonomous_plan_enabled} label="Allow safety-gated autonomous replies" description={!platform.autonomous_replies_enabled ? 'Disabled by the platform operator.' : !platform.autonomous_plan_enabled ? 'Not included in this workspace plan.' : 'Requires human-review mode to be off plus an autonomous Agent and channel; every send still passes all runtime safety gates.'} />
                    </div>
                </SectionCard>
                <SectionCard title="Budgets and retention">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <label className="text-sm font-medium text-slate-700">Log retention days<Input className="mt-1" type="number" min="1" max={platform.max_retention_days} value={form.data.log_retention_days} onChange={(e) => form.setData('log_retention_days', e.target.value)} /></label>
                        <label className="text-sm font-medium text-slate-700">Monthly token budget<Input className="mt-1" type="number" min="1" placeholder="No workspace override" value={form.data.monthly_token_budget || ''} onChange={(e) => form.setData('monthly_token_budget', e.target.value || null)} /></label>
                        <label className="text-sm font-medium text-slate-700">Monthly cost budget (USD)<Input className="mt-1" type="number" min="0.01" step="0.01" placeholder="No workspace override" value={form.data.monthly_cost_budget || ''} onChange={(e) => form.setData('monthly_cost_budget', e.target.value || null)} /></label>
                    </div>
                </SectionCard>
                <Button type="submit" loading={form.processing}>Save AI settings</Button>
            </form>
        </AIShell>
    );
}
