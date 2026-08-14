import EngagementShell from '@/Components/Tenant/Engagement/EngagementShell';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import Textarea from '@/Components/UI/Textarea';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    BarChart3, CheckCircle2, ClipboardList, ExternalLink, FileText, Globe2,
    ListFilter, ListPlus, MessageSquareText, Plus, Save, UsersRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';

const tabs = [
    { value: 'forms', label: 'Forms', icon: FileText },
    { value: 'help-center', label: 'Help center', icon: Globe2 },
    { value: 'csat', label: 'CSAT', icon: MessageSquareText },
    { value: 'segments', label: 'Segments', icon: ListFilter },
    { value: 'lists', label: 'Lists', icon: UsersRound },
    { value: 'submissions', label: 'Submissions', icon: ClipboardList },
];

const createTabs = ['forms', 'csat', 'segments', 'lists'];

function formatDate(value) {
    if (!value) return 'Not available';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function ResourceCard({ title, badge, description, children }) {
    return (
        <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-soft transition hover:border-brand-200 hover:shadow-soft-lg">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="truncate text-sm font-semibold text-slate-900">{title}</h3>
                    {description && <p className="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{description}</p>}
                </div>
                {badge && <Badge tone="brand">{badge}</Badge>}
            </div>
            {children && <div className="mt-3 border-t border-slate-100 pt-3">{children}</div>}
        </article>
    );
}

function ResourceGrid({ items, empty }) {
    if (!items.length) return empty;
    return <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">{items}</div>;
}

function TabNavigation({ active, onChange, counts }) {
    return (
        <nav className="flex gap-1 overflow-x-auto border-b border-slate-200 px-3 pt-2 sm:px-5" aria-label="Customer experience sections">
            {tabs.map((tab) => {
                const Icon = tab.icon;
                const selected = active === tab.value;
                return (
                    <button
                        key={tab.value}
                        type="button"
                        onClick={() => onChange(tab.value)}
                        aria-current={selected ? 'page' : undefined}
                        className={`flex shrink-0 items-center gap-1.5 border-b-2 px-2.5 py-3 text-xs font-semibold transition ${selected ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'}`}
                    >
                        <Icon className="h-3.5 w-3.5" />
                        {tab.label}
                        {counts[tab.value] !== undefined && <span className={`rounded-full px-1.5 py-0.5 text-[10px] ${selected ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500'}`}>{counts[tab.value]}</span>}
                    </button>
                );
            })}
        </nav>
    );
}

export default function Index({ segments = [], lists = [], forms = [], submissions = [], helpCenter, surveys = [], csat = {} }) {
    const canManage = usePage().props.auth?.permissions?.includes('experience.manage');
    const [tab, setTab] = useState('forms');
    const [createOpen, setCreateOpen] = useState(false);

    const segment = useForm({ name: '', description: '', field: 'status', operator: 'equals', value: 'active' });
    const list = useForm({ name: '', description: '' });
    const supportForm = useForm({ name: '', slug: '', description: '' });
    const survey = useForm({ name: 'Standard CSAT', question: 'How satisfied are you with the support you received?', resource_type: 'conversation', scale: 5 });
    const help = useForm({
        name: helpCenter?.name || 'Help Center',
        slug: helpCenter?.slug || 'help',
        welcome_text: helpCenter?.welcome_text || 'How can we help?',
        primary_color: helpCenter?.primary_color || '#2563eb',
        active: Boolean(helpCenter?.active),
        allow_ticket_submission: helpCenter ? Boolean(helpCenter.allow_ticket_submission) : true,
        allow_portal: helpCenter ? Boolean(helpCenter.allow_portal) : true,
    });

    const counts = useMemo(() => ({
        forms: forms.length,
        csat: surveys.length,
        segments: segments.length,
        lists: lists.length,
        submissions: submissions.length,
    }), [forms, surveys, segments, lists, submissions]);

    const activeForm = { forms: supportForm, csat: survey, segments: segment, lists: list }[tab];
    const createLabels = {
        forms: ['Create support form', 'Create form'],
        csat: ['Create CSAT survey', 'Create survey'],
        segments: ['Create dynamic segment', 'Create segment'],
        lists: ['Create contact list', 'Create list'],
    };

    const closeCreate = () => {
        if (!activeForm?.processing) setCreateOpen(false);
    };

    const submitCreate = (event) => {
        event.preventDefault();
        const config = {
            forms: ['tenant.admin.experience.forms.store', supportForm],
            csat: ['tenant.admin.experience.csat.store', survey],
            segments: ['tenant.admin.experience.segments.store', segment],
            lists: ['tenant.admin.experience.lists.store', list],
        }[tab];
        if (!config) return;
        const [routeName, form] = config;
        form.post(route(routeName), {
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpen(false);
                form.reset();
            },
        });
    };

    const switchTab = (next) => {
        setCreateOpen(false);
        setTab(next);
    };

    return (
        <EngagementShell
            title="Customer experience"
            description="Create self-service touchpoints, collect feedback, and organize audiences from one place."
            actions={canManage && createTabs.includes(tab) && <Button variant="brand" size="sm" icon={Plus} onClick={() => setCreateOpen(true)}>{createLabels[tab][1]}</Button>}
        >
            <Head title="Customer experience" />

            <div className="-mx-4 -mt-4 mb-5 sm:-mx-5 sm:-mt-5">
                <TabNavigation active={tab} onChange={switchTab} counts={counts} />
            </div>

            {tab === 'forms' && (
                <ResourceGrid
                    items={forms.map((item) => (
                        <ResourceCard key={item.public_uuid} title={item.name} description={item.description || 'Public support request form'} badge={item.active ? 'Active' : 'Disabled'}>
                            <a className="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800" href={route('tenant.forms.show', item.slug)} target="_blank" rel="noreferrer">Open public form <ExternalLink className="h-3.5 w-3.5" /></a>
                        </ResourceCard>
                    ))}
                    empty={<EmptyState icon={FileText} title="No support forms yet" description="Create a simple public form so customers can send structured requests without signing in." action={canManage && <Button variant="brand" icon={Plus} onClick={() => setCreateOpen(true)}>Create form</Button>} />}
                />
            )}

            {tab === 'help-center' && (
                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
                    <form onSubmit={(event) => { event.preventDefault(); help.put(route('tenant.admin.experience.help-center.update'), { preserveScroll: true }); }} className="rounded-xl border border-slate-200 bg-white shadow-soft">
                        <div className="border-b border-slate-100 px-5 py-4"><h2 className="text-sm font-semibold text-slate-900">Help center settings</h2><p className="mt-1 text-xs text-slate-500">Control the public identity and available self-service options.</p></div>
                        <div className="grid gap-4 p-5 sm:grid-cols-2">
                            <FormField label="Name" required error={help.errors.name}><Input value={help.data.name} onChange={(event) => help.setData('name', event.target.value)} /></FormField>
                            <FormField label="URL slug" required error={help.errors.slug} hint="Used in the public help center URL."><Input value={help.data.slug} onChange={(event) => help.setData('slug', event.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '-'))} /></FormField>
                            <FormField label="Welcome message" className="sm:col-span-2" error={help.errors.welcome_text}><Textarea rows={3} value={help.data.welcome_text} onChange={(event) => help.setData('welcome_text', event.target.value)} /></FormField>
                            <FormField label="Brand color" error={help.errors.primary_color}><div className="flex gap-2"><Input type="color" value={help.data.primary_color} onChange={(event) => help.setData('primary_color', event.target.value)} className="w-14 px-1" /><Input value={help.data.primary_color} onChange={(event) => help.setData('primary_color', event.target.value)} /></div></FormField>
                        </div>
                        <div className="space-y-3 border-t border-slate-100 px-5 py-4">
                            <Switch checked={help.data.active} onChange={(value) => help.setData('active', value)} label="Publish help center" description="Make the help center available to customers." />
                            <Switch checked={help.data.allow_ticket_submission} onChange={(value) => help.setData('allow_ticket_submission', value)} label="Allow support requests" description="Customers can open support requests from the help center." />
                            <Switch checked={help.data.allow_portal} onChange={(value) => help.setData('allow_portal', value)} label="Allow customer portal" description="Customers can access their own support history." />
                        </div>
                        {canManage && <div className="flex justify-end border-t border-slate-100 bg-slate-50/50 px-5 py-3"><Button type="submit" variant="brand" size="sm" icon={Save} loading={help.processing}>Save settings</Button></div>}
                    </form>
                    <aside className="rounded-xl border border-slate-200 bg-gradient-to-b from-brand-50 to-white p-5">
                        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-brand-700 shadow-soft"><Globe2 className="h-5 w-5" /></span>
                        <h3 className="mt-4 text-sm font-semibold text-slate-900">Publication status</h3>
                        <div className="mt-2"><Badge tone={help.data.active ? 'brand' : 'neutral'}>{help.data.active ? 'Ready for customers' : 'Not published'}</Badge></div>
                        <p className="mt-3 text-xs leading-5 text-slate-500">Preview the customer-facing experience after saving to verify the welcome message, branding, and available actions.</p>
                    </aside>
                </div>
            )}

            {tab === 'csat' && (
                <div className="space-y-5">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Metric icon={BarChart3} label="Average rating" value={Number(csat.average || 0).toFixed(2)} />
                        <Metric icon={CheckCircle2} label="Responses" value={csat.responses || 0} />
                        <Metric icon={MessageSquareText} label="Active surveys" value={surveys.filter((item) => item.active).length} />
                    </div>
                    <ResourceGrid items={surveys.map((item) => <ResourceCard key={item.public_uuid} title={item.name} description={item.question} badge={`${item.scale}-point scale`} />)} empty={<EmptyState icon={MessageSquareText} title="No satisfaction surveys" description="Create a CSAT survey to measure the quality of resolved conversations or tickets." action={canManage && <Button variant="brand" icon={Plus} onClick={() => setCreateOpen(true)}>Create survey</Button>} />} />
                </div>
            )}

            {tab === 'segments' && <ResourceGrid items={segments.map((item) => <ResourceCard key={item.public_uuid} title={item.name} description={item.description || 'Dynamic audience based on contact attributes'} badge={`${item.cached_count || 0} contacts`}><p className="truncate font-mono text-[11px] text-slate-500">{item.conditions}</p></ResourceCard>)} empty={<EmptyState icon={ListFilter} title="No dynamic segments" description="Build reusable audiences that update automatically as contact attributes change." action={canManage && <Button variant="brand" icon={Plus} onClick={() => setCreateOpen(true)}>Create segment</Button>} />} />}

            {tab === 'lists' && <ResourceGrid items={lists.map((item) => <ResourceCard key={item.public_uuid} title={item.name} description={item.description || 'Static contact list'} badge={item.active ? 'Active' : 'Disabled'} />)} empty={<EmptyState icon={ListPlus} title="No contact lists" description="Create a static list for a hand-picked audience or one-time workflow." action={canManage && <Button variant="brand" icon={Plus} onClick={() => setCreateOpen(true)}>Create list</Button>} />} />}

            {tab === 'submissions' && (
                submissions.length ? <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-soft"><div className="overflow-x-auto"><table className="min-w-full divide-y divide-slate-200 text-sm"><thead className="bg-slate-50"><tr><th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Submission</th><th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th><th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Received</th></tr></thead><tbody className="divide-y divide-slate-100">{submissions.map((item) => <tr key={item.public_uuid} className="hover:bg-slate-50"><td className="px-4 py-3 font-mono text-xs text-slate-700">{item.public_uuid}</td><td className="px-4 py-3"><Badge tone={item.status === 'processed' ? 'brand' : 'neutral'}>{item.status}</Badge></td><td className="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{formatDate(item.submitted_at)}</td></tr>)}</tbody></table></div></div> : <EmptyState icon={ClipboardList} title="No form submissions yet" description="New customer requests submitted through your public forms will appear here." />
            )}

            <Modal open={createOpen} onClose={closeCreate} title={createLabels[tab]?.[0]} description="Complete the details below. You can adjust the configuration later." size="lg" footer={<><Button variant="secondary" onClick={closeCreate}>Cancel</Button><Button variant="brand" onClick={submitCreate} loading={activeForm?.processing}>{createLabels[tab]?.[1]}</Button></>}>
                <form id="experience-create-form" onSubmit={submitCreate} className="space-y-4">
                    {tab === 'forms' && <><FormField label="Form name" required error={supportForm.errors.name}><Input autoFocus value={supportForm.data.name} onChange={(event) => supportForm.setData('name', event.target.value)} placeholder="Contact support" /></FormField><FormField label="Public URL slug" required error={supportForm.errors.slug} hint="Letters, numbers, dashes, and underscores only."><Input value={supportForm.data.slug} onChange={(event) => supportForm.setData('slug', event.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '-'))} placeholder="contact-support" /></FormField><FormField label="Description" error={supportForm.errors.description}><Textarea rows={3} value={supportForm.data.description} onChange={(event) => supportForm.setData('description', event.target.value)} placeholder="Tell customers when to use this form." /></FormField></>}
                    {tab === 'csat' && <><FormField label="Survey name" required error={survey.errors.name}><Input autoFocus value={survey.data.name} onChange={(event) => survey.setData('name', event.target.value)} /></FormField><FormField label="Question" required error={survey.errors.question}><Textarea rows={3} value={survey.data.question} onChange={(event) => survey.setData('question', event.target.value)} /></FormField><div className="grid gap-4 sm:grid-cols-2"><FormField label="Measure after" required error={survey.errors.resource_type}><Select value={survey.data.resource_type} onChange={(event) => survey.setData('resource_type', event.target.value)}><option value="conversation">Conversation</option><option value="ticket">Ticket</option></Select></FormField><FormField label="Rating scale" required error={survey.errors.scale}><Select value={survey.data.scale} onChange={(event) => survey.setData('scale', Number(event.target.value))}>{[3, 5, 10].map((value) => <option key={value} value={value}>{value} points</option>)}</Select></FormField></div></>}
                    {tab === 'segments' && <><FormField label="Segment name" required error={segment.errors.name}><Input autoFocus value={segment.data.name} onChange={(event) => segment.setData('name', event.target.value)} placeholder="Active customers" /></FormField><FormField label="Description" error={segment.errors.description}><Textarea rows={2} value={segment.data.description} onChange={(event) => segment.setData('description', event.target.value)} /></FormField><div className="grid gap-4 sm:grid-cols-2"><FormField label="Contact field" required error={segment.errors.field}><Select value={segment.data.field} onChange={(event) => segment.setData('field', event.target.value)}>{['status', 'preferred_language', 'country', 'company_id'].map((value) => <option key={value} value={value}>{value.replaceAll('_', ' ')}</option>)}</Select></FormField><FormField label="Operator" required error={segment.errors.operator}><Select value={segment.data.operator} onChange={(event) => segment.setData('operator', event.target.value)}>{['equals', 'not_equals', 'contains'].map((value) => <option key={value} value={value}>{value.replaceAll('_', ' ')}</option>)}</Select></FormField></div><FormField label="Value" required error={segment.errors.value}><Input value={segment.data.value} onChange={(event) => segment.setData('value', event.target.value)} /></FormField></>}
                    {tab === 'lists' && <><FormField label="List name" required error={list.errors.name}><Input autoFocus value={list.data.name} onChange={(event) => list.setData('name', event.target.value)} placeholder="Product launch audience" /></FormField><FormField label="Description" error={list.errors.description}><Textarea rows={3} value={list.data.description} onChange={(event) => list.setData('description', event.target.value)} placeholder="Explain who belongs in this list." /></FormField></>}
                </form>
            </Modal>
        </EngagementShell>
    );
}

function Metric({ icon: Icon, label, value }) {
    return <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-soft"><span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><Icon className="h-4 w-4" /></span><div><p className="text-lg font-bold text-slate-900">{value}</p><p className="text-xs text-slate-500">{label}</p></div></div>;
}
