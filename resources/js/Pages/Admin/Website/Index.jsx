import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Tabs from '@/Components/UI/Tabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, FileText, Plus } from 'lucide-react';
import { useState } from 'react';

function PagesTab({ pages }) {
    const [deleting, setDeleting] = useState(null);

    return (
        <SectionCard
            title="Pages"
            description={'The page with slug "home" renders at your site\'s root URL.'}
            actions={<Button href={route('superadmin.website.pages.create')} variant="brand" icon={Plus}>Create page</Button>}
        >
            {pages.length ? (
                <div className="divide-y divide-slate-100">
                    {pages.map((page) => (
                        <div key={page.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <div className="font-semibold text-slate-900">{page.title}</div>
                                <div className="mt-0.5 font-mono text-xs text-slate-500">/{page.slug === 'home' ? '' : page.slug} · {page.sections_count} section{page.sections_count === 1 ? '' : 's'}</div>
                            </div>
                            <div className="flex items-center gap-3">
                                <StatusBadge status={page.status} />
                                {page.status === 'published' && (
                                    <a href={`/${page.slug === 'home' ? '' : page.slug}`} target="_blank" rel="noopener noreferrer" className="flex items-center gap-1 text-sm font-medium text-navy-800 hover:text-brand-700">
                                        Preview <ExternalLink className="h-3 w-3" />
                                    </a>
                                )}
                                <Button href={route('superadmin.website.pages.edit', page.id)} variant="ghost" size="sm">Edit</Button>
                                <Button variant="ghost" size="sm" onClick={() => setDeleting(page)}>Delete</Button>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <EmptyState icon={FileText} title="No pages yet" description={'Create a page with slug "home" to populate your site\'s homepage.'} action={<Button href={route('superadmin.website.pages.create')} variant="brand" icon={Plus}>Create page</Button>} />
            )}

            <ConfirmDialog
                open={!!deleting}
                title={`Delete "${deleting?.title}"?`}
                variant="danger"
                confirmLabel="Delete page"
                onCancel={() => setDeleting(null)}
                onConfirm={() => { router.delete(route('superadmin.website.pages.destroy', deleting.id)); setDeleting(null); }}
            >
                This removes the page and its sections from the public site. This cannot be undone.
            </ConfirmDialog>
        </SectionCard>
    );
}

function LinkRow({ item, fields, onSave, onDelete }) {
    const [editing, setEditing] = useState(false);
    const { data, setData, processing } = useForm(Object.fromEntries(fields.map((field) => [field.key, item[field.key] ?? ''])));

    if (!editing) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                    <div className="font-semibold text-slate-900">{item.label}</div>
                    <div className="mt-0.5 text-xs text-slate-500">{item.url}{item.group ? ` · ${item.group}` : ''}</div>
                </div>
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="sm" onClick={() => setEditing(true)}>Edit</Button>
                    <Button variant="ghost" size="sm" onClick={onDelete}>Remove</Button>
                </div>
            </div>
        );
    }

    return (
        <form
            onSubmit={(event) => { event.preventDefault(); onSave(data, () => setEditing(false)); }}
            className="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 py-3 md:grid-cols-[1fr_1fr_auto]"
        >
            {fields.map((field) => (
                <Input key={field.key} placeholder={field.label} value={data[field.key]} onChange={(event) => setData(field.key, event.target.value)} />
            ))}
            <div className="flex gap-2">
                <Button type="submit" variant="brand" size="sm" loading={processing}>Save</Button>
                <Button type="button" variant="secondary" size="sm" onClick={() => setEditing(false)}>Cancel</Button>
            </div>
        </form>
    );
}

function LinkListEditor({ title, description, items, fields, storeRoute, updateRouteName, destroyRouteName }) {
    const { data, setData, post, processing, reset } = useForm(Object.fromEntries(fields.map((field) => [field.key, ''])));
    const [removing, setRemoving] = useState(null);

    const addItem = (event) => {
        event.preventDefault();
        post(route(storeRoute), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <SectionCard title={title} description={description}>
            <div className="divide-y divide-slate-100">
                {items.map((item) => (
                    <LinkRow
                        key={item.id}
                        item={item}
                        fields={fields}
                        onDelete={() => setRemoving(item)}
                        onSave={(payload, done) => router.put(route(updateRouteName, item.id), payload, { preserveScroll: true, onSuccess: done })}
                    />
                ))}
                {!items.length && <p className="py-6 text-center text-sm text-slate-500">Nothing added yet.</p>}
            </div>
            <form onSubmit={addItem} className="mt-4 grid gap-3 rounded-md border border-dashed border-slate-300 p-3 md:grid-cols-[1fr_1fr_auto]">
                {fields.map((field) => (
                    <Input key={field.key} placeholder={field.label} value={data[field.key]} onChange={(event) => setData(field.key, event.target.value)} />
                ))}
                <Button type="submit" variant="brand" size="sm" loading={processing}>Add</Button>
            </form>

            <ConfirmDialog
                open={!!removing}
                title={`Remove "${removing?.label}"?`}
                variant="danger"
                confirmLabel="Remove"
                onCancel={() => setRemoving(null)}
                onConfirm={() => { router.delete(route(destroyRouteName, removing.id), { preserveScroll: true }); setRemoving(null); }}
            />
        </SectionCard>
    );
}

function SettingsTab({ settings }) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm(settings);

    const submit = (event) => {
        event.preventDefault();
        put(route('superadmin.website.settings.update'), { preserveScroll: true });
    };

    const fields = [
        { key: 'site_name', label: 'Site name' },
        { key: 'logo_url', label: 'Logo URL' },
        { key: 'primary_color', label: 'Primary color (hex)' },
        { key: 'contact_email', label: 'Contact email' },
        { key: 'social_twitter', label: 'Twitter/X URL' },
        { key: 'social_linkedin', label: 'LinkedIn URL' },
    ];

    return (
        <SectionCard title="Site settings" description="Used by the public site's header, footer, and metadata.">
            <form onSubmit={submit}>
                <div className="grid gap-5 md:grid-cols-2">
                    {fields.map((field) => (
                        <FormField key={field.key} id={field.key} label={field.label} error={errors[field.key]}>
                            <Input id={field.key} value={data[field.key]} error={!!errors[field.key]} onChange={(event) => setData(field.key, event.target.value)} />
                        </FormField>
                    ))}
                </div>
                {data.primary_color && (
                    <div className="mt-4 flex items-center gap-2 text-xs text-slate-500">
                        Preview: <span className="h-5 w-5 rounded border border-slate-200" style={{ backgroundColor: data.primary_color }} /> {data.primary_color}
                    </div>
                )}
                <div className="mt-5 flex items-center justify-end gap-3">
                    {recentlySuccessful && <span className="text-xs font-semibold text-brand-700">Saved</span>}
                    <Button type="submit" variant="brand" loading={processing}>Save settings</Button>
                </div>
            </form>
        </SectionCard>
    );
}

export default function Index({ pages, navigation, footerLinks, settings }) {
    const [tab, setTab] = useState('pages');
    const tabs = [
        { value: 'pages', label: 'Pages' },
        { value: 'navigation', label: 'Navigation' },
        { value: 'footer', label: 'Footer' },
        { value: 'settings', label: 'Branding' },
    ];

    return (
        <AuthenticatedLayout header={<PageHeader title="Website" subtitle="Manage your public marketing site: pages, navigation, footer, and branding." />}>
            <Head title="Website" />

            <div className="space-y-6">
                <Tabs items={tabs} active={tab} onChange={setTab} />

                {tab === 'pages' && <PagesTab pages={pages} />}

                {tab === 'navigation' && (
                    <LinkListEditor
                        title="Navigation"
                        description="Header menu links shown on every public page."
                        items={navigation}
                        fields={[{ key: 'label', label: 'Label' }, { key: 'url', label: 'URL' }]}
                        storeRoute="superadmin.website.navigation.store"
                        updateRouteName="superadmin.website.navigation.update"
                        destroyRouteName="superadmin.website.navigation.destroy"
                    />
                )}

                {tab === 'footer' && (
                    <LinkListEditor
                        title="Footer links"
                        description="Grouped links shown in the site footer."
                        items={footerLinks}
                        fields={[{ key: 'label', label: 'Label' }, { key: 'url', label: 'URL' }, { key: 'group', label: 'Group (optional)' }]}
                        storeRoute="superadmin.website.footer-links.store"
                        updateRouteName="superadmin.website.footer-links.update"
                        destroyRouteName="superadmin.website.footer-links.destroy"
                    />
                )}

                {tab === 'settings' && <SettingsTab settings={settings} />}
            </div>
        </AuthenticatedLayout>
    );
}
