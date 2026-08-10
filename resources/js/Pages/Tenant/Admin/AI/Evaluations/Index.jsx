import AIShell from '@/Components/AI/AIShell';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { router, useForm } from '@inertiajs/react';
import { Play } from 'lucide-react';

function AddCase({ suite }) {
    const form = useForm({ name: '', category: 'grounding', input: '', assertion_type: 'citations_required', assertion_value: '' });
    const submit = (e) => { e.preventDefault(); form.post(route('tenant.admin.ai.evaluations.cases.store', suite.public_uuid), { preserveScroll: true, onSuccess: () => form.reset() }); };
    return <form className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2" onSubmit={submit}><Input placeholder="Case name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /><Input placeholder="Category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} required /><Textarea className="sm:col-span-2" placeholder="Input sent to the deployed agent" value={form.data.input} onChange={(e) => form.setData('input', e.target.value)} required /><Select value={form.data.assertion_type} onChange={(e) => form.setData('assertion_type', e.target.value)}>{['citations_required','contains','not_contains','regex','max_latency_ms'].map((type) => <option key={type}>{type}</option>)}</Select><Input placeholder="Assertion value (when required)" value={form.data.assertion_value} onChange={(e) => form.setData('assertion_value', e.target.value)} /><Button size="sm" type="submit" loading={form.processing}>Add case</Button></form>;
}

export default function Evaluations({ suites, agents, canManage, canRun }) {
    const form = useForm({ name: '', description: '', agent_id: '' });
    const submit = (e) => { e.preventDefault(); form.post(route('tenant.admin.ai.evaluations.store'), { preserveScroll: true, onSuccess: () => form.reset() }); };
    return <AIShell title="AI evaluations" description="Build repeatable suites for grounding, safety, quality, and latency. Runs execute only when requested and use the deployed agent version captured at queue time.">
        {canManage && <SectionCard title="Create evaluation suite"><form className="grid gap-3 sm:grid-cols-3" onSubmit={submit}><Input placeholder="Suite name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /><Select value={form.data.agent_id} onChange={(e) => form.setData('agent_id', Number(e.target.value))} required><option value="">Deployed agent</option>{agents.map((agent) => <option key={agent.id} value={agent.id}>{agent.name}</option>)}</Select><Button type="submit" loading={form.processing}>Create suite</Button><Input className="sm:col-span-3" placeholder="Description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></form></SectionCard>}
        <div className={`${canManage ? 'mt-6' : ''} space-y-4`}>{suites.map((suite) => { const latest = suite.runs?.[0]; return <SectionCard key={suite.public_uuid} title={suite.name} description={`${suite.agent?.name || 'No agent'} · ${suite.cases.length} cases`} actions={latest && <Badge tone={latest.status === 'completed' ? (Number(latest.pass_rate) === 100 ? 'success' : 'warning') : 'neutral'}>{latest.status}{latest.pass_rate !== null ? ` · ${latest.pass_rate}%` : ''}</Badge>}>
            {suite.description && <p className="text-sm text-slate-600">{suite.description}</p>}<div className="mt-3 flex flex-wrap gap-2">{suite.cases.map((testCase) => <Badge key={testCase.public_uuid} tone="neutral">{testCase.name}</Badge>)}</div>{canRun && <Button className="mt-4" size="sm" icon={Play} disabled={!suite.cases.length} onClick={() => router.post(route('tenant.admin.ai.evaluations.run', suite.public_uuid), {}, { preserveScroll: true })}>Run suite</Button>}{canManage && <AddCase suite={suite} />}
        </SectionCard>; })}{!suites.length && <div className="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No evaluation suites yet.</div>}</div>
    </AIShell>;
}
