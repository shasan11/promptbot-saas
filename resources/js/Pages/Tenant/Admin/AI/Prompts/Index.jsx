import AIShell from '@/Components/AI/AIShell';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { router, useForm } from '@inertiajs/react';
import { Plus, UploadCloud } from 'lucide-react';
import { useState } from 'react';

const blank = { name: '', type: 'task', description: '', template: '', variables_text: '' };

export default function Prompts({ prompts, canManage }) {
    const [editing, setEditing] = useState(null); const form = useForm(blank);
    const reset = () => { setEditing(null); form.setData(blank); form.clearErrors(); };
    const edit = (prompt) => { setEditing(prompt.public_uuid); form.setData({ ...prompt, variables_text: prompt.variables.join(', ') }); };
    const submit = (e) => {
        e.preventDefault();
        const variables = form.data.variables_text.split(',').map((item) => item.trim()).filter(Boolean);
        form.transform((data) => ({ ...data, variables, variables_text: undefined }));
        const options = { preserveScroll: true, onSuccess: reset, onFinish: () => form.transform((data) => data) };
        editing ? form.put(route('tenant.admin.ai.prompts.update', editing), options) : form.post(route('tenant.admin.ai.prompts.store'), options);
    };
    return <AIShell title="Prompt library" description="Maintain reusable, versioned prompt templates. Variables use explicit {{ variable }} placeholders; templates are never evaluated as code." actions={editing && <Button variant="secondary" icon={Plus} onClick={reset}>New prompt</Button>}>
        {canManage && <SectionCard title={editing ? 'Edit prompt draft' : 'Create prompt draft'}><form className="space-y-4" onSubmit={submit}><div className="grid gap-4 sm:grid-cols-2">
            <label className="text-sm font-medium text-slate-700">Name<Input className="mt-1" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /></label>
            <label className="text-sm font-medium text-slate-700">Type<Select className="mt-1" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>{['system','task','classification','summary','draft','tool'].map((type) => <option key={type}>{type}</option>)}</Select></label>
            <label className="text-sm font-medium text-slate-700 sm:col-span-2">Description<Input className="mt-1" value={form.data.description || ''} onChange={(e) => form.setData('description', e.target.value)} /></label>
            <label className="text-sm font-medium text-slate-700 sm:col-span-2">Template<Textarea className="mt-1 min-h-44 font-mono text-xs" value={form.data.template} onChange={(e) => form.setData('template', e.target.value)} placeholder="Summarize {{ conversation }} without inventing facts." required /></label>
            <label className="text-sm font-medium text-slate-700 sm:col-span-2">Variables <span className="font-normal text-slate-400">(comma separated)</span><Input className="mt-1" value={form.data.variables_text} onChange={(e) => form.setData('variables_text', e.target.value)} placeholder="conversation, customer_name" /></label>
        </div>{Object.keys(form.errors).length > 0 && <p className="text-sm text-rose-600">{Object.values(form.errors)[0]}</p>}<Button type="submit" loading={form.processing}>{editing ? 'Save draft' : 'Create draft'}</Button></form></SectionCard>}
        <div className={`${canManage ? 'mt-6' : ''} space-y-4`}>{prompts.map((prompt) => <SectionCard key={prompt.public_uuid} title={prompt.name} description={`${prompt.key} · ${prompt.type}`} actions={<Badge tone={prompt.status === 'active' ? 'success' : 'neutral'}>{prompt.status}{prompt.active_version ? ` · v${prompt.active_version}` : ''}</Badge>}>
            <pre className="max-h-36 overflow-auto whitespace-pre-wrap rounded-md bg-slate-50 p-3 text-xs leading-5 text-slate-600">{prompt.template}</pre><div className="mt-3 flex flex-wrap items-center justify-between gap-2"><p className="text-xs text-slate-400">Variables: {prompt.variables.length ? prompt.variables.join(', ') : 'none'}</p>{canManage && <div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => edit(prompt)}>Edit</Button><Button size="sm" icon={UploadCloud} onClick={() => router.post(route('tenant.admin.ai.prompts.publish', prompt.public_uuid), {}, { preserveScroll: true })}>Publish</Button></div>}</div>
        </SectionCard>)}</div>
    </AIShell>;
}
