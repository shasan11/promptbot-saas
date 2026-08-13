import Panel from '@/Components/Portal/Panel';
import PortalLayout from '@/Layouts/PortalLayout';
import { useForm } from '@inertiajs/react';

export default function Create({ workspaces, selectedWorkspace = '' }) {
    const form = useForm({ tenant_id: selectedWorkspace, subject: '', description: '', category: selectedWorkspace ? 'workspace' : 'general', priority: 'normal', attachment: null });
    const cls = 'mt-1.5 w-full rounded-lg border-slate-300';
    return <PortalLayout title="Create support ticket"><Panel><form onSubmit={event => { event.preventDefault(); form.post(route('portal.support.store'), { forceFormData: true }); }} className="mx-auto max-w-2xl space-y-5">
        <div className="grid gap-4 sm:grid-cols-2"><label className="text-sm font-medium">Category<select className={cls} value={form.data.category} onChange={event => form.setData('category', event.target.value)}>{['general','billing','technical','workspace'].map(value => <option key={value}>{value}</option>)}</select></label><label className="text-sm font-medium">Workspace<select className={cls} value={form.data.tenant_id} onChange={event => form.setData('tenant_id', event.target.value)}><option value="">General account</option>{workspaces.map(workspace => <option key={workspace.id} value={workspace.id}>{workspace.company_name}</option>)}</select></label></div>
        <label className="block text-sm font-medium">Subject<input className={cls} value={form.data.subject} onChange={event => form.setData('subject', event.target.value)} required />{form.errors.subject && <span className="text-xs text-rose-600">{form.errors.subject}</span>}</label>
        <label className="block text-sm font-medium">How can we help?<textarea rows="7" className={cls} value={form.data.description} onChange={event => form.setData('description', event.target.value)} required />{form.errors.description && <span className="text-xs text-rose-600">{form.errors.description}</span>}</label>
        <label className="block text-sm font-medium">Attachment (optional)<input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.csv" onChange={event => form.setData('attachment', event.target.files[0] || null)} className="mt-2 block w-full text-sm" /><span className="mt-1 block text-xs text-slate-500">Private to this ticket. Maximum 10 MB.</span>{form.errors.attachment && <span className="text-xs text-rose-600">{form.errors.attachment}</span>}</label>
        <label className="block text-sm font-medium">Priority<select className={cls} value={form.data.priority} onChange={event => form.setData('priority', event.target.value)}>{['low','normal','high','urgent'].map(value => <option key={value}>{value}</option>)}</select></label>
        <button disabled={form.processing} className="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{form.processing ? 'Creating…' : 'Create ticket'}</button>
    </form></Panel></PortalLayout>;
}
