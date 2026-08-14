import CustomersShell from '@/Components/Tenant/Customers/CustomersShell';
import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card, SectionCard } from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Archive, Building2, CalendarDays, Clock3, Globe2, Languages, Mail, MapPin, Pencil, Phone, Tag, UserRound } from 'lucide-react';
import { useState } from 'react';

const tones = { active: 'brand', vip: 'warning', blocked: 'danger', inactive: 'neutral' };

function formatDate(value, fallback = 'Not recorded') {
    if (!value) return fallback;
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function DetailRow({ icon: Icon, label, children }) {
    return <div className="flex gap-3 py-3 first:pt-0 last:pb-0"><span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><Icon className="h-4 w-4" /></span><div className="min-w-0"><dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</dt><dd className="mt-0.5 break-words text-sm font-medium text-slate-700">{children}</dd></div></div>;
}

export default function Show({ contact }) {
    const permissions = usePage().props.auth?.permissions || [];
    const [archiveOpen, setArchiveOpen] = useState(false);
    const archive = () => router.delete(route('tenant.admin.customers.contacts.destroy', contact.public_uuid), { onFinish: () => setArchiveOpen(false) });

    return (
        <CustomersShell
            title="Contact details"
            description="Customer identity, ownership, attributes, and support history."
            actions={<>{permissions.includes('customers.update') && <Button href={route('tenant.admin.customers.contacts.edit', contact.public_uuid)} variant="secondary" icon={Pencil}>Edit contact</Button>}{permissions.includes('customers.delete') && <Button variant="danger" icon={Archive} onClick={() => setArchiveOpen(true)}>Archive</Button>}</>}
        >
            <Head title={contact.display_name} />

            <section className="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-soft">
                <div className="h-20 bg-gradient-to-r from-brand-50 via-blue-50 to-slate-50" />
                <div className="flex flex-col gap-4 px-5 pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex min-w-0 items-end gap-4">
                        <div className="-mt-8 rounded-full border-4 border-white bg-white shadow-sm"><Avatar name={contact.display_name} size="lg" className="h-16 w-16 text-lg" /></div>
                        <div className="min-w-0 pb-1"><div className="flex flex-wrap items-center gap-2"><h1 className="truncate text-xl font-bold tracking-tight text-navy-900">{contact.display_name}</h1><Badge tone={tones[contact.status] || 'neutral'}>{contact.status}</Badge></div><p className="mt-1 truncate text-sm text-slate-500">{contact.email || contact.phone || 'No primary contact information'}</p></div>
                    </div>
                    {contact.tags?.length > 0 && <div className="flex flex-wrap gap-1.5 sm:max-w-sm sm:justify-end">{contact.tags.map((tag) => <span key={tag.public_uuid} className="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium text-slate-600" style={{ borderColor: `${tag.color || '#cbd5e1'}80`, backgroundColor: `${tag.color || '#f1f5f9'}12` }}><span className="h-2 w-2 rounded-full" style={{ backgroundColor: tag.color || '#94a3b8' }} />{tag.name}</span>)}</div>}
                </div>
            </section>

            <div className="grid gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
                <aside className="space-y-5">
                    <SectionCard title="Contact information">
                        <dl className="divide-y divide-slate-100">
                            <DetailRow icon={Mail} label="Email">{contact.email ? <a href={`mailto:${contact.email}`} className="text-brand-700 hover:underline">{contact.email}</a> : 'Not provided'}</DetailRow>
                            {contact.secondary_email && <DetailRow icon={Mail} label="Secondary email"><a href={`mailto:${contact.secondary_email}`} className="text-brand-700 hover:underline">{contact.secondary_email}</a></DetailRow>}
                            <DetailRow icon={Phone} label="Phone">{contact.phone ? <a href={`tel:${contact.phone}`} className="text-brand-700 hover:underline">{contact.phone}</a> : 'Not provided'}</DetailRow>
                            {contact.secondary_phone && <DetailRow icon={Phone} label="Secondary phone">{contact.secondary_phone}</DetailRow>}
                        </dl>
                    </SectionCard>

                    <SectionCard title="Customer context">
                        <dl className="divide-y divide-slate-100">
                            <DetailRow icon={Building2} label="Company">{contact.company ? <Link href={route('tenant.admin.customers.companies.show', contact.company.public_uuid)} className="text-brand-700 hover:underline">{contact.company.name}</Link> : 'No company'}</DetailRow>
                            <DetailRow icon={UserRound} label="Account owner">{contact.owner?.name || 'Unassigned'}</DetailRow>
                            <DetailRow icon={Globe2} label="Source">{contact.source ? contact.source.replaceAll('_', ' ') : 'Not recorded'}</DetailRow>
                            <DetailRow icon={MapPin} label="Country">{contact.country || 'Not provided'}</DetailRow>
                            <DetailRow icon={Languages} label="Language">{contact.preferred_language || 'Not provided'}</DetailRow>
                        </dl>
                    </SectionCard>
                </aside>

                <main className="min-w-0 space-y-5">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Card className="flex items-center gap-3 p-4"><span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><Clock3 className="h-4 w-4" /></span><div className="min-w-0"><p className="truncate text-xs text-slate-500">Last contacted</p><p className="mt-0.5 truncate text-sm font-semibold text-slate-800">{formatDate(contact.last_contacted_at, 'Never')}</p></div></Card>
                        <Card className="flex items-center gap-3 p-4"><span className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700"><CalendarDays className="h-4 w-4" /></span><div className="min-w-0"><p className="truncate text-xs text-slate-500">Customer since</p><p className="mt-0.5 truncate text-sm font-semibold text-slate-800">{formatDate(contact.created_at)}</p></div></Card>
                        <Card className="flex items-center gap-3 p-4"><span className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-700"><Tag className="h-4 w-4" /></span><div><p className="text-xs text-slate-500">Tags</p><p className="mt-0.5 text-sm font-semibold text-slate-800">{contact.tags?.length || 0}</p></div></Card>
                    </div>

                    {(contact.contact_points?.length > 0 || contact.custom_field_values?.length > 0) && <SectionCard title="Additional details" description="Alternative contact points and workspace-specific customer attributes."><div className="grid gap-x-6 gap-y-4 sm:grid-cols-2">{contact.contact_points?.map((point) => <div key={point.id} className="rounded-lg bg-slate-50 p-3"><p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{point.label || point.type}</p><p className="mt-1 break-words text-sm font-medium text-slate-700">{point.value}</p></div>)}{contact.custom_field_values?.map((value) => <div key={value.id} className="rounded-lg bg-slate-50 p-3"><p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{value.field.label}</p><p className="mt-1 break-words text-sm font-medium text-slate-700">{Array.isArray(value.value) ? value.value.join(', ') : String(value.value ?? '—')}</p></div>)}</div></SectionCard>}

                    <SectionCard title="Activity timeline" description="A chronological record of customer and support activity.">
                        {contact.activities?.length ? <ol className="relative ml-2 border-l border-slate-200">{contact.activities.map((activity, index) => <li key={activity.public_uuid} className={`relative ml-6 ${index === contact.activities.length - 1 ? '' : 'pb-6'}`}><span className="absolute -left-[1.8rem] top-0.5 flex h-3 w-3 rounded-full border-2 border-white bg-brand-500 ring-2 ring-brand-100" /><div className="rounded-lg border border-slate-100 bg-slate-50/60 px-4 py-3"><p className="text-sm font-medium leading-5 text-slate-800">{activity.description}</p><div className="mt-2 flex flex-wrap items-center gap-x-2 text-xs text-slate-500"><span className="font-medium text-slate-600">{activity.actor?.name || activity.actor_name || 'System'}</span><span aria-hidden="true">•</span><time>{formatDate(activity.occurred_at)}</time></div></div></li>)}</ol> : <div className="py-10 text-center"><Clock3 className="mx-auto h-7 w-7 text-slate-300" /><p className="mt-2 text-sm font-medium text-slate-700">No activity yet</p><p className="mt-1 text-xs text-slate-500">Updates and support interactions will appear here.</p></div>}
                    </SectionCard>
                </main>
            </div>

            <ConfirmDialog open={archiveOpen} title={`Archive ${contact.display_name}?`} confirmLabel="Archive contact" variant="danger" onCancel={() => setArchiveOpen(false)} onConfirm={archive}>
                Their history will be retained and the contact can be restored later.
            </ConfirmDialog>
        </CustomersShell>
    );
}
