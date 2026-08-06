import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950';

function Panel({ title, subtitle, children, actions }) {
    return (
        <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-base font-bold text-slate-950">{title}</h2>
                    {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
                </div>
                {actions}
            </div>
            {children}
        </section>
    );
}

function PagesTab({ pages }) {
    const destroy = (page) => {
        if (window.confirm(`Delete the page "${page.title}"? This cannot be undone.`)) {
            router.delete(route('superadmin.website.pages.destroy', page.id));
        }
    };

    return (
        <Panel
            title="Pages"
            subtitle={'The page with slug "home" renders at your site\'s root URL.'}
            actions={<Link href={route('superadmin.website.pages.create')} className="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Create page</Link>}
        >
            {pages.length ? (
                <div className="divide-y divide-slate-100">
                    {pages.map((page) => (
                        <div key={page.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <div className="font-semibold text-slate-950">{page.title}</div>
                                <div className="mt-0.5 font-mono text-xs text-slate-500">/{page.slug === 'home' ? '' : page.slug} &middot; {page.sections_count} section{page.sections_count === 1 ? '' : 's'}</div>
                            </div>
                            <div className="flex items-center gap-3">
                                <StatusBadge status={page.status} />
                                <Link href={route('superadmin.website.pages.edit', page.id)} className="text-sm font-semibold text-slate-600 hover:text-slate-950">Edit</Link>
                                <button type="button" onClick={() => destroy(page)} className="text-sm font-semibold text-rose-600 hover:text-rose-800">Delete</button>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">No pages yet. Create a page with slug "home" to populate your site's homepage.</div>
            )}
        </Panel>
    );
}

function LinkRow({ item, fields, onSave, onDelete }) {
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing } = useForm(Object.fromEntries(fields.map((field) => [field.key, item[field.key] ?? ''])));

    if (!editing) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                    <div className="font-semibold text-slate-950">{item.label}</div>
                    <div className="mt-0.5 text-xs text-slate-500">{item.url}{item.group ? ` · ${item.group}` : ''}</div>
                </div>
                <div className="flex items-center gap-3">
                    <button type="button" onClick={() => setEditing(true)} className="text-sm font-semibold text-slate-600 hover:text-slate-950">Edit</button>
                    <button type="button" onClick={onDelete} className="text-sm font-semibold text-rose-600 hover:text-rose-800">Remove</button>
                </div>
            </div>
        );
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                onSave(data, () => setEditing(false));
            }}
            className="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 py-3 md:grid-cols-[1fr_1fr_auto]"
        >
            {fields.map((field) => (
                <input key={field.key} className={inputClass} placeholder={field.label} value={data[field.key]} onChange={(event) => setData(field.key, event.target.value)} />
            ))}
            <div className="flex gap-2">
                <button disabled={processing} type="submit" className="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white shadow-sm">Save</button>
                <button type="button" onClick={() => setEditing(false)} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm">Cancel</button>
            </div>
        </form>
    );
}

function LinkListEditor({ title, subtitle, items, fields, storeRoute, updateRouteName, destroyRouteName }) {
    const { data, setData, post, processing, reset } = useForm(Object.fromEntries(fields.map((field) => [field.key, ''])));

    const addItem = (event) => {
        event.preventDefault();
        post(route(storeRoute), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <Panel title={title} subtitle={subtitle}>
            <div className="divide-y divide-slate-100">
                {items.map((item) => (
                    <LinkRow
                        key={item.id}
                        item={item}
                        fields={fields}
                        onDelete={() => window.confirm(`Remove "${item.label}"?`) && router.delete(route(destroyRouteName, item.id), { preserveScroll: true })}
                        onSave={(payload, done) => router.put(route(updateRouteName, item.id), payload, { preserveScroll: true, onSuccess: done })}
                    />
                ))}
                {!items.length && <p className="py-6 text-center text-sm text-slate-500">Nothing added yet.</p>}
            </div>
            <form onSubmit={addItem} className="mt-4 grid gap-3 rounded-md border border-dashed border-slate-300 p-3 md:grid-cols-[1fr_1fr_auto]">
                {fields.map((field) => (
                    <input key={field.key} className={inputClass} placeholder={field.label} value={data[field.key]} onChange={(event) => setData(field.key, event.target.value)} />
                ))}
                <button disabled={processing} className="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white shadow-sm">Add</button>
            </form>
        </Panel>
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
        <Panel title="Site settings" subtitle="Used by the public site's header, footer, and metadata.">
            <form onSubmit={submit} className="grid gap-5 md:grid-cols-2">
                {fields.map((field) => (
                    <label key={field.key} className="block">
                        <span className="text-sm font-semibold text-slate-700">{field.label}</span>
                        <input className={`${inputClass} mt-2`} value={data[field.key]} onChange={(event) => setData(field.key, event.target.value)} />
                        {errors[field.key] && <p className="mt-1 text-xs font-semibold text-rose-600">{errors[field.key]}</p>}
                    </label>
                ))}
                <div className="md:col-span-2 flex items-center justify-end gap-3">
                    {recentlySuccessful && <span className="text-xs font-semibold text-emerald-600">Saved</span>}
                    <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Save settings</button>
                </div>
            </form>
        </Panel>
    );
}

export default function Index({ pages, navigation, footerLinks, settings }) {
    const [tab, setTab] = useState('pages');
    const tabs = ['pages', 'navigation', 'footer', 'settings'];

    return (
        <AuthenticatedLayout header={<PageHeader title="Website" subtitle="Manage your public marketing site: pages, navigation, footer, and branding." />}>
            <Head title="Website" />

            <div className="space-y-6">
                <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                    <div className="flex min-w-max gap-1">
                        {tabs.map((item) => (
                            <button key={item} type="button" onClick={() => setTab(item)} className={`rounded-md px-3 py-2 text-sm font-semibold capitalize transition ${tab === item ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                                {item}
                            </button>
                        ))}
                    </div>
                </div>

                {tab === 'pages' && <PagesTab pages={pages} />}

                {tab === 'navigation' && (
                    <LinkListEditor
                        title="Navigation"
                        subtitle="Header menu links shown on every public page."
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
                        subtitle="Grouped links shown in the site footer."
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
