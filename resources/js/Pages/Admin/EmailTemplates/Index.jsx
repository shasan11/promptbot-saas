import PageHeader from '@/Components/Superadmin/PageHeader';
import { SectionCard } from '@/Components/UI/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
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
    return <AuthenticatedLayout header={<PageHeader title="Email templates" subtitle="Edit, preview, activate, and test customer lifecycle email content." />}><Head title="Email templates" /><div className="space-y-5">{templates.map(template => <TemplateEditor key={template.id} template={template} samples={sampleValues} />)}</div></AuthenticatedLayout>;
}
