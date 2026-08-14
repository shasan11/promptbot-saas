import PageHeader from '@/Components/Superadmin/PageHeader';
import { SectionCard } from '@/Components/UI/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { MailPlus, Send } from 'lucide-react';
import { useMemo, useState } from 'react';

const replaceVariables = (value, samples) => Object.entries(samples).reduce(
    (result, [key, sample]) => result.replaceAll(`{{${key}}}`, sample),
    value || '',
);

function TemplateEditor({ template, samples }) {
    const form = useForm({ subject: template.subject || '', body: template.body || '', status: template.status || 'draft' });
    const test = useForm({ recipient: '' });
    const [preview, setPreview] = useState(false);
    const previewBody = useMemo(() => replaceVariables(form.data.body, samples), [form.data.body, samples]);

    const submit = event => {
        event.preventDefault();
        form.put(route('superadmin.communications.email-templates.update', template.id), { preserveScroll: true });
    };

    return <SectionCard title={template.key.replaceAll('_', ' ')} description={`Language: ${template.language} · Channel: ${template.channel}`}>
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 md:grid-cols-[1fr_10rem]">
                <label className="text-sm font-medium text-slate-700">Subject
                    <input required value={form.data.subject} onChange={event => form.setData('subject', event.target.value)} className="mt-1 w-full rounded-lg border-slate-300" />
                </label>
                <label className="text-sm font-medium text-slate-700">Status
                    <select value={form.data.status} onChange={event => form.setData('status', event.target.value)} className="mt-1 w-full rounded-lg border-slate-300"><option value="draft">Draft</option><option value="active">Active</option></select>
                </label>
            </div>
            <label className="block text-sm font-medium text-slate-700">HTML body
                <textarea required rows="12" value={form.data.body} onChange={event => form.setData('body', event.target.value)} className="mt-1 w-full rounded-lg border-slate-300 font-mono text-sm" />
            </label>
            <div className="rounded-lg bg-slate-50 p-3 text-xs text-slate-600"><span className="font-semibold">Available variables:</span> {(template.variables || []).map(variable => `{{${variable}}}`).join(', ')}</div>
            {Object.values(form.errors).map(error => <p key={error} className="text-sm text-rose-600">{error}</p>)}
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="flex items-end gap-2">
                    <label className="text-sm font-medium text-slate-700">Test recipient
                        <input type="email" value={test.data.recipient} onChange={event => test.setData('recipient', event.target.value)} className="mt-1 block rounded-lg border-slate-300" placeholder="you@example.com" />
                    </label>
                    <button type="button" disabled={!test.data.recipient || test.processing} onClick={() => test.post(route('superadmin.communications.email-templates.test', template.id), { preserveScroll: true })} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold disabled:opacity-50">Send test</button>
                </div>
                <div className="flex gap-2"><button type="button" onClick={() => setPreview(value => !value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">{preview ? 'Hide preview' : 'Preview'}</button><button disabled={form.processing} className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Save template</button></div>
            </div>
            {test.errors.recipient && <p className="text-sm text-rose-600">{test.errors.recipient}</p>}
            {preview && <div className="overflow-hidden rounded-lg border border-slate-200"><div className="border-b bg-slate-50 px-4 py-2 text-sm"><strong>Subject:</strong> {replaceVariables(form.data.subject, samples)}</div><iframe title={`${template.key} preview`} sandbox="" srcDoc={previewBody} className="h-80 w-full bg-white" /></div>}
        </form>
    </SectionCard>;
}

export default function Index({ templates, sampleValues }) {
    const bulk = useForm({ audience: 'active', subject: '', body: '<h1>Hello from {{platform_name}}</h1><p>Write your announcement here.</p>', recipients: '' });
    const [showBulk, setShowBulk] = useState(false);
    return <AuthenticatedLayout header={<PageHeader title="Email templates" subtitle="Manage lifecycle messages, previews, tests, and queued customer announcements." actions={<button onClick={() => setShowBulk(value => !value)} className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white"><MailPlus className="h-4 w-4" />{showBulk ? 'Close composer' : 'Compose bulk email'}</button>} />}><Head title="Email templates" /><div className="space-y-5">
        {showBulk && <SectionCard title="Bulk email composer" description="Messages are queued individually so normal mail delivery and retry handling still apply.">
            <form onSubmit={event => { event.preventDefault(); bulk.post(route('superadmin.communications.bulk-email.store'), { preserveScroll: true, onSuccess: () => bulk.reset() }); }} className="space-y-4">
                <div className="grid gap-4 md:grid-cols-[220px_1fr]"><label className="text-sm font-medium text-slate-700">Audience<select value={bulk.data.audience} onChange={event => bulk.setData('audience',event.target.value)} className="mt-1.5 w-full rounded-lg border-slate-300"><option value="active">All active portal users</option><option value="all">All portal users</option><option value="custom">Custom email list</option></select></label><label className="text-sm font-medium text-slate-700">Subject<input value={bulk.data.subject} onChange={event => bulk.setData('subject',event.target.value)} required className="mt-1.5 w-full rounded-lg border-slate-300" /></label></div>
                {bulk.data.audience === 'custom' && <label className="block text-sm font-medium text-slate-700">Recipients<textarea value={bulk.data.recipients} onChange={event => bulk.setData('recipients',event.target.value)} rows="3" placeholder="one@example.com, two@example.com" className="mt-1.5 w-full rounded-lg border-slate-300 text-sm" />{bulk.errors.recipients && <span className="mt-1 block text-xs text-rose-600">{bulk.errors.recipients}</span>}</label>}
                <label className="block text-sm font-medium text-slate-700">HTML message<textarea value={bulk.data.body} onChange={event => bulk.setData('body',event.target.value)} required rows="10" className="mt-1.5 w-full rounded-lg border-slate-300 font-mono text-sm" /></label>
                {Object.entries(bulk.errors).filter(([key]) => key !== 'recipients').map(([key,error]) => <p key={key} className="text-sm text-rose-600">{error}</p>)}
                <div className="flex justify-end"><button disabled={bulk.processing} className="inline-flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"><Send className="h-4 w-4" />{bulk.processing ? 'Queuing…' : 'Queue bulk email'}</button></div>
            </form>
        </SectionCard>}
        <div className="rounded-xl border border-slate-200 bg-white px-5 py-4"><p className="text-sm font-semibold text-slate-900">{templates.length} lifecycle templates</p><p className="mt-1 text-xs text-slate-500">Each template supports variables, a safe preview, activation status, and test delivery.</p></div>
        {templates.map(template => <TemplateEditor key={template.id} template={template} samples={sampleValues} />)}
    </div></AuthenticatedLayout>;
}
