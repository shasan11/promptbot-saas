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
import { BarChart3, Check, ExternalLink, FileText, Globe2, GripVertical, Image, LayoutTemplate, Palette, Plus, Search, Sparkles, Type } from 'lucide-react';
import { useEffect, useState } from 'react';

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

function FieldControl({ field, value, onChange }) {
    if (field.type === 'select') {
        return <select aria-label={field.label} value={value ?? ''} onChange={(event) => onChange(event.target.value)} className="rounded-lg border-slate-300 text-sm">
            {(field.options || []).map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </select>;
    }
    if (field.type === 'checkbox') {
        return <label className="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm"><input type="checkbox" checked={!!value} onChange={(event) => onChange(event.target.checked)} className="rounded" />{field.label}</label>;
    }
    return <Input placeholder={field.label} value={value ?? ''} onChange={(event) => onChange(event.target.value)} />;
}

function fieldDefaults(fields, item = {}) {
    return Object.fromEntries(fields.map((field) => [field.key, item[field.key] ?? field.default ?? (field.type === 'checkbox' ? false : '')]));
}

function LinkRow({ item, fields, onSave, onDelete, reorderable = false }) {
    const [editing, setEditing] = useState(false);
    const { data, setData, processing } = useForm(fieldDefaults(fields, item));

    if (!editing) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div className="flex items-center gap-2">
                    {reorderable && <GripVertical className="h-4 w-4 cursor-grab text-slate-400" aria-hidden="true" />}
                    <div>
                    <div className="font-semibold text-slate-900">{item.label}</div>
                    <div className="mt-0.5 text-xs text-slate-500">{item.url}{item.group ? ` · ${item.group}` : ''}{item.menu_group ? ` · ${item.menu_group}` : ''}{item.type ? ` · ${item.type}` : ''}{item.parent_id ? ' · child item' : ''}{item.is_active === false ? ' · inactive' : ''}</div>
                    </div>
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
            className="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 py-3 md:grid-cols-2 xl:grid-cols-4"
        >
            {fields.map((field) => (
                <FieldControl key={field.key} field={field} value={data[field.key]} onChange={(value) => setData(field.key, value)} />
            ))}
            <div className="flex gap-2">
                <Button type="submit" variant="brand" size="sm" loading={processing}>Save</Button>
                <Button type="button" variant="secondary" size="sm" onClick={() => setEditing(false)}>Cancel</Button>
            </div>
        </form>
    );
}

function LinkListEditor({ title, description, items, fields, storeRoute, updateRouteName, destroyRouteName, reorderRouteName = null }) {
    const { data, setData, post, processing, reset } = useForm(fieldDefaults(fields));
    const [removing, setRemoving] = useState(null);
    const [orderedItems, setOrderedItems] = useState(items);
    const [draggedId, setDraggedId] = useState(null);
    useEffect(() => setOrderedItems(items), [items]);

    const addItem = (event) => {
        event.preventDefault();
        post(route(storeRoute), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <SectionCard title={title} description={description}>
            <div className="divide-y divide-slate-100">
                {orderedItems.map((item) => (
                    <div key={item.id} draggable={!!reorderRouteName} onDragStart={() => setDraggedId(item.id)} onDragOver={(event) => event.preventDefault()} onDrop={() => {
                        if (!draggedId || draggedId === item.id) return;
                        const next = [...orderedItems];
                        const [dragged] = next.splice(next.findIndex((entry) => entry.id === draggedId), 1);
                        next.splice(next.findIndex((entry) => entry.id === item.id), 0, dragged);
                        setOrderedItems(next);
                        setDraggedId(null);
                        router.put(route(reorderRouteName), { ordered_ids: next.map((entry) => entry.id) }, { preserveScroll: true });
                    }} className={draggedId === item.id ? 'opacity-50' : ''}>
                    <LinkRow
                        item={item}
                        fields={fields}
                        reorderable={!!reorderRouteName}
                        onDelete={() => setRemoving(item)}
                        onSave={(payload, done) => router.put(route(updateRouteName, item.id), payload, { preserveScroll: true, onSuccess: done })}
                    />
                    </div>
                ))}
                {!orderedItems.length && <p className="py-6 text-center text-sm text-slate-500">Nothing added yet.</p>}
            </div>
            <form onSubmit={addItem} className="mt-4 grid gap-3 rounded-md border border-dashed border-slate-300 p-3 md:grid-cols-2 xl:grid-cols-4">
                {fields.map((field) => (
                    <FieldControl key={field.key} field={field} value={data[field.key]} onChange={(value) => setData(field.key, value)} />
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

const themePresets = [
    { name: 'Emerald', colors: ['#064E3B', '#475569', '#059669'] },
    { name: 'Ocean', colors: ['#0C4A6E', '#475569', '#0284C7'] },
    { name: 'Indigo', colors: ['#312E81', '#475569', '#4F46E5'] },
    { name: 'Rose', colors: ['#881337', '#475569', '#E11D48'] },
];

function SettingsPanel({ icon: Icon, title, description, children }) {
    return <section className="rounded-xl border border-slate-200 bg-white shadow-soft">
        <div className="flex gap-3 border-b border-slate-100 px-5 py-4">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"><Icon className="h-4 w-4" /></span>
            <div><h3 className="font-semibold text-slate-950">{title}</h3><p className="mt-0.5 text-xs leading-5 text-slate-500">{description}</p></div>
        </div>
        <div className="p-5">{children}</div>
    </section>;
}

function ColorControl({ label, hint, value, onChange, error }) {
    const display = /^#[0-9a-f]{6}$/i.test(value || '') ? value : '#000000';
    return <FormField label={label} hint={hint} error={error}>
        <div className="flex rounded-lg border border-slate-300 bg-white p-1 shadow-soft focus-within:border-emerald-600 focus-within:ring-2 focus-within:ring-emerald-100">
            <label className="relative h-10 w-12 shrink-0 cursor-pointer overflow-hidden rounded-md border border-slate-200" style={{ backgroundColor: display }}>
                <input aria-label={`${label} color picker`} type="color" value={display} onChange={event => onChange(event.target.value.toUpperCase())} className="absolute inset-0 h-full w-full cursor-pointer opacity-0" />
            </label>
            <input aria-label={`${label} hex value`} value={value || ''} onChange={event => onChange(event.target.value.toUpperCase())} placeholder="#059669" className="min-w-0 flex-1 border-0 bg-transparent px-3 font-mono text-sm uppercase focus:ring-0" />
        </div>
    </FormField>;
}

function ChoiceCards({ value, onChange, options }) {
    return <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">{options.map(option => {
        const active = value === option.value;
        return <button key={option.value} type="button" onClick={() => onChange(option.value)} className={`rounded-lg border px-3 py-2.5 text-left transition ${active ? 'border-emerald-600 bg-emerald-50 ring-1 ring-emerald-600' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'}`}>
            <span className={`block text-sm font-semibold ${active ? 'text-emerald-800' : 'text-slate-800'}`}>{option.label}</span>
            {option.hint && <span className="mt-0.5 block text-[11px] text-slate-500">{option.hint}</span>}
        </button>;
    })}</div>;
}

function SettingsTab({ settings }) {
    const { data, setData, post, processing, errors, recentlySuccessful, transform } = useForm({ ...settings, logo_file: null, logo_dark_file: null, favicon_file: null });
    const [section, setSection] = useState('brand');

    const submit = (event) => {
        event.preventDefault();
        transform(payload => ({ ...payload, _method: 'put' }));
        post(route('superadmin.website.settings.update'), { preserveScroll: true, forceFormData: true });
    };

    const nav = [
        { value: 'brand', label: 'Brand assets', hint: 'Name, logos and favicon', icon: Image },
        { value: 'theme', label: 'Colors & style', hint: 'Palette, type and shapes', icon: Palette },
        { value: 'content', label: 'Footer & contact', hint: 'Public contact information', icon: Globe2 },
        { value: 'seo', label: 'SEO & tracking', hint: 'Search and analytics', icon: Search },
    ];

    const primary = /^#[0-9a-f]{6}$/i.test(data.primary_color || '') ? data.primary_color : '#064E3B';
    const accent = /^#[0-9a-f]{6}$/i.test(data.accent_color || '') ? data.accent_color : '#059669';
    const field = (key, label, hint, props = {}) => <FormField id={key} label={label} hint={hint} error={errors[key]}><Input id={key} value={data[key] || ''} onChange={event => setData(key, event.target.value)} {...props} /></FormField>;

    return (
        <form onSubmit={submit} className="space-y-5">
            <div className="overflow-hidden rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-950 to-emerald-800 p-5 text-white shadow-soft">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex gap-3"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10"><Sparkles className="h-5 w-5" /></span><div><h2 className="font-semibold">Make the website feel like your brand</h2><p className="mt-1 max-w-2xl text-sm text-emerald-100">Choose a section and save once to apply the changes across the public website and every account screen.</p></div></div>
                    <a href="/" target="_blank" rel="noopener noreferrer" className="inline-flex shrink-0 items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm font-semibold text-white hover:bg-white/15">Open website <ExternalLink className="h-4 w-4" /></a>
                </div>
            </div>

            <div className="grid gap-5 xl:grid-cols-[220px_minmax(0,1fr)]">
                <nav aria-label="Customization sections" className="h-fit rounded-xl border border-slate-200 bg-white p-2 shadow-soft xl:sticky xl:top-5">
                    {nav.map(item => <button key={item.value} type="button" onClick={() => setSection(item.value)} className={`flex w-full items-start gap-3 rounded-lg px-3 py-3 text-left transition ${section === item.value ? 'bg-emerald-50 text-emerald-900' : 'text-slate-700 hover:bg-slate-50'}`}>
                        <item.icon className={`mt-0.5 h-4 w-4 shrink-0 ${section === item.value ? 'text-emerald-700' : 'text-slate-400'}`} />
                        <span><span className="block text-sm font-semibold">{item.label}</span><span className="mt-0.5 block text-[11px] text-slate-500">{item.hint}</span></span>
                    </button>)}
                </nav>

                <div className="space-y-5">
                    {section === 'brand' && <SettingsPanel icon={Image} title="Brand assets" description="These are used across the public website and account screens.">
                        <div className="space-y-5">
                            {field('site_name', 'Website name', 'Shown in page titles and whenever a logo cannot load.', { required: true })}
                            <div className="grid gap-4 sm:grid-cols-3">{[
                                ['logo_file', 'logo_url', 'Light-background logo', 'Your normal full logo.'],
                                ['logo_dark_file', 'logo_dark_url', 'Dark-background logo', 'A light logo for dark surfaces.'],
                                ['favicon_file', 'favicon_url', 'Browser icon', 'A square PNG, ICO or WEBP.'],
                            ].map(([fileKey, urlKey, label, hint]) => <label key={fileKey} className="group rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 transition hover:border-emerald-400 hover:bg-emerald-50/40">
                                <span className="flex h-16 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white p-2">{data[fileKey] ? <span className="truncate text-xs font-semibold text-emerald-700">{data[fileKey].name}</span> : data[urlKey] ? <img src={data[urlKey]} alt="" className="max-h-full max-w-full object-contain" /> : <Image className="h-6 w-6 text-slate-300" />}</span>
                                <span className="mt-3 block text-sm font-semibold text-slate-900">{label}</span><span className="mt-1 block text-xs leading-5 text-slate-500">{hint}</span>
                                <input type="file" accept={fileKey === 'favicon_file' ? 'image/png,image/x-icon,image/vnd.microsoft.icon,image/webp' : 'image/png,image/jpeg,image/webp'} onChange={event => setData(fileKey, event.target.files?.[0] || null)} className="mt-3 block w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-white file:px-2 file:py-1.5 file:font-semibold file:text-emerald-700" />
                                {errors[fileKey] && <span className="mt-2 block text-xs text-rose-600">{errors[fileKey]}</span>}
                            </label>)}</div>
                        </div>
                    </SettingsPanel>}

                    {section === 'theme' && <>
                        <SettingsPanel icon={Palette} title="Color palette" description="Start with a preset or fine-tune each color.">
                            <div className="grid gap-2 sm:grid-cols-2">{themePresets.map(preset => {
                                const active = preset.colors[0].toLowerCase() === primary.toLowerCase() && preset.colors[2].toLowerCase() === accent.toLowerCase();
                                return <button key={preset.name} type="button" onClick={() => { setData('primary_color', preset.colors[0]); setData('secondary_color', preset.colors[1]); setData('accent_color', preset.colors[2]); }} className={`flex items-center justify-between rounded-lg border p-3 text-left transition ${active ? 'border-emerald-600 bg-emerald-50 ring-1 ring-emerald-600' : 'border-slate-200 hover:border-slate-300'}`}>
                                    <span><span className="block text-sm font-semibold text-slate-900">{preset.name}</span><span className="mt-1 flex gap-1">{preset.colors.map(color => <i key={color} className="h-4 w-4 rounded-full border border-white shadow" style={{ backgroundColor: color }} />)}</span></span>{active && <Check className="h-4 w-4 text-emerald-700" />}
                                </button>;
                            })}</div>
                            <div className="mt-5 grid gap-5 sm:grid-cols-3">
                                <ColorControl label="Primary" hint="Headings and dark brand areas." value={data.primary_color} onChange={value => setData('primary_color', value)} error={errors.primary_color} />
                                <ColorControl label="Secondary" hint="Supporting text and details." value={data.secondary_color} onChange={value => setData('secondary_color', value)} error={errors.secondary_color} />
                                <ColorControl label="Accent" hint="Buttons, links and focus states." value={data.accent_color} onChange={value => setData('accent_color', value)} error={errors.accent_color} />
                            </div>
                        </SettingsPanel>
                        <SettingsPanel icon={Type} title="Typography" description="Pick clear, web-friendly typefaces for headings and body copy.">
                            <div className="grid gap-5 sm:grid-cols-2">{[['heading_font', 'Heading font'], ['body_font', 'Body font']].map(([key, label]) => <FormField key={key} label={label} error={errors[key]}><select value={data[key] || ''} onChange={event => setData(key, event.target.value)} className="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-600 focus:ring-emerald-600"><option value="">Use website default</option>{['Inter','Manrope','Poppins','Roboto','system-ui'].map(font => <option key={font}>{font}</option>)}</select></FormField>)}</div>
                        </SettingsPanel>
                        <SettingsPanel icon={LayoutTemplate} title="Shape & layout" description="Friendly choices instead of raw CSS values.">
                            <div className="space-y-5">
                                <FormField label="Button shape"><ChoiceCards value={data.button_radius || ''} onChange={value => setData('button_radius', value)} options={[{value:'4px',label:'Subtle'},{value:'8px',label:'Soft'},{value:'12px',label:'Rounded'},{value:'9999px',label:'Pill'}]} /></FormField>
                                <FormField label="Card shape"><ChoiceCards value={data.card_radius || ''} onChange={value => setData('card_radius', value)} options={[{value:'8px',label:'Subtle'},{value:'12px',label:'Soft'},{value:'16px',label:'Rounded'},{value:'24px',label:'Extra round'}]} /></FormField>
                                <FormField label="Content width"><ChoiceCards value={data.container_width || ''} onChange={value => setData('container_width', value)} options={[{value:'1024px',label:'Compact',hint:'Focused reading'},{value:'1152px',label:'Balanced',hint:'Most websites'},{value:'1280px',label:'Wide',hint:'More visual space'},{value:'1440px',label:'Extra wide',hint:'Large screens'}]} /></FormField>
                            </div>
                        </SettingsPanel>
                    </>}

                    {section === 'content' && <SettingsPanel icon={Globe2} title="Footer & contact" description="Keep the information visitors use to trust and contact you in one place.">
                        <div className="space-y-5">
                            <FormField id="footer_description" label="Short company description" hint="One or two clear sentences work best." error={errors.footer_description}><textarea id="footer_description" rows="3" value={data.footer_description || ''} onChange={event => setData('footer_description', event.target.value)} className="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-600 focus:ring-emerald-600" /></FormField>
                            <div className="grid gap-5 sm:grid-cols-2">{field('copyright_text', 'Copyright name', 'The current year is added automatically.', { placeholder: data.site_name || 'PromptBot' })}{field('contact_email', 'Public contact email', 'Shown to visitors who need help.', { type: 'email', placeholder: 'hello@example.com' })}</div>
                            <div className="grid gap-5 sm:grid-cols-2">{field('social_twitter', 'X / Twitter profile', null, { placeholder: 'https://x.com/yourbrand' })}{field('social_linkedin', 'LinkedIn profile', null, { placeholder: 'https://linkedin.com/company/yourbrand' })}</div>
                        </div>
                    </SettingsPanel>}

                    {section === 'seo' && <>
                        <SettingsPanel icon={Search} title="Search appearance" description="Control how the website appears in search results and social shares.">
                            <div className="space-y-5">
                                {field('default_meta_title_format', 'Page title format', 'Use {title} and {site_name} as placeholders.', { placeholder: '{title} · {site_name}' })}
                                <FormField id="default_description" label="Default search description" hint="Aim for one useful sentence, around 150 characters." error={errors.default_description}><textarea id="default_description" rows="3" value={data.default_description || ''} onChange={event => setData('default_description', event.target.value)} className="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-600 focus:ring-emerald-600" /></FormField>
                                <div className="grid gap-5 sm:grid-cols-2">{field('canonical_base_url', 'Website address', null, { placeholder: 'https://example.com' })}{field('default_og_image', 'Social sharing image', 'Recommended size: 1200 × 630 px.', { placeholder: '/images/social-card.jpg' })}</div>
                                <FormField label="Social card style"><select value={data.twitter_card_type || ''} onChange={event => setData('twitter_card_type', event.target.value)} className="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-600 focus:ring-emerald-600"><option value="">Large image (recommended)</option><option value="summary_large_image">Large image</option><option value="summary">Compact summary</option></select></FormField>
                            </div>
                        </SettingsPanel>
                        <SettingsPanel icon={BarChart3} title="Verification & analytics" description="Optional IDs for search consoles and visitor analytics.">
                            <div className="grid gap-5 sm:grid-cols-2">{field('google_verification', 'Google verification code')}{field('bing_verification', 'Bing verification code')}{field('google_analytics_id', 'Google Analytics ID', null, { placeholder: 'G-XXXXXXXXXX' })}{field('google_tag_manager_id', 'Google Tag Manager ID', null, { placeholder: 'GTM-XXXXXXX' })}{field('meta_pixel_id', 'Meta Pixel ID')}</div>
                            <FormField id="robots_content" label="Robots.txt additions" hint="Advanced: leave empty unless you know which crawlers you need to control." error={errors.robots_content} className="mt-5"><textarea id="robots_content" rows="4" value={data.robots_content || ''} onChange={event => setData('robots_content', event.target.value)} className="w-full rounded-lg border-slate-300 font-mono text-xs focus:border-emerald-600 focus:ring-emerald-600" /></FormField>
                        </SettingsPanel>
                    </>}
                </div>

            </div>

            <div className="sticky bottom-3 z-20 flex items-center justify-between rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur">
                <div>{recentlySuccessful ? <span className="flex items-center gap-2 text-sm font-semibold text-emerald-700"><Check className="h-4 w-4" />Changes saved and applied</span> : <span className="text-xs text-slate-500">Save once to apply changes across the website and account screens.</span>}</div>
                <Button type="submit" variant="brand" loading={processing}>{processing ? 'Saving…' : 'Save changes'}</Button>
            </div>
        </form>
    );
}

function RedirectsTab({ redirects }) {
    const form = useForm({ from_path: '', to_url: '', status_code: 301 });
    return <SectionCard title="Redirects" description="Create safe 301 or 302 redirects; loops and self-redirects are rejected.">
        <form onSubmit={(event) => { event.preventDefault(); form.post(route('superadmin.website.redirects.store'), { onSuccess: () => form.reset() }); }} className="grid gap-3 sm:grid-cols-[1fr_1fr_110px_auto]"><Input placeholder="/old-path" value={form.data.from_path} onChange={(event) => form.setData('from_path', event.target.value)} /><Input placeholder="/new-path or https://…" value={form.data.to_url} onChange={(event) => form.setData('to_url', event.target.value)} /><select value={form.data.status_code} onChange={(event) => form.setData('status_code', event.target.value)} className="rounded-lg border-slate-300 text-sm"><option value="301">301</option><option value="302">302</option></select><Button type="submit" variant="brand">Add</Button></form>
        <div className="mt-5 divide-y">{redirects.map((redirect) => <div key={redirect.id} className="grid grid-cols-[1fr_1fr_auto_auto] gap-3 py-3 text-sm"><span className="font-mono">{redirect.from_path}</span><span>{redirect.to_url}</span><span>{redirect.status_code} · {redirect.hit_count} hits</span><button onClick={() => router.delete(route('superadmin.website.redirects.destroy', redirect.id))} className="font-semibold text-rose-600">Remove</button></div>)}</div>
    </SectionCard>;
}

function MediaCard({ item }) {
    const form = useForm({ file: null, alt_text: item.alt_text || '', caption: item.caption || '' });
    const save = event => { event.preventDefault(); form.post(route('superadmin.website.media.update', item.id), { forceFormData: true, preserveScroll: true }); };
    return <div className="overflow-hidden rounded-lg border"><img src={item.url} alt={item.alt_text || ''} className="aspect-video w-full object-cover" /><form onSubmit={save} className="space-y-2 p-3"><p className="truncate text-sm font-medium" title={item.filename}>{item.filename}</p><p className="text-xs text-slate-500">{item.mime_type} · {item.width}×{item.height} · {Math.round(item.size / 1024)} KB</p><Input placeholder="Alt text" value={form.data.alt_text} onChange={event => form.setData('alt_text', event.target.value)} /><Input placeholder="Caption" value={form.data.caption} onChange={event => form.setData('caption', event.target.value)} /><input type="file" accept="image/png,image/jpeg,image/webp,image/gif" onChange={event => form.setData('file', event.target.files[0])} className="w-full text-xs" />{Object.values(form.errors).map(error => <p key={error} className="text-xs text-rose-600">{error}</p>)}<div className="flex flex-wrap gap-2"><Button type="submit" variant="secondary" size="sm" loading={form.processing}>Save / replace</Button><Button type="button" variant="ghost" size="sm" onClick={() => navigator.clipboard.writeText(item.url)}>Copy URL</Button><Button type="button" variant="ghost" size="sm" onClick={() => confirm('Delete this media item if unused?') && router.delete(route('superadmin.website.media.destroy', item.id), { preserveScroll: true })}>Delete unused</Button></div></form></div>;
}

function MediaTab({ media }) {
    const form = useForm({ file: null, alt_text: '', caption: '' });
    const [search, setSearch] = useState('');
    const [type, setType] = useState('');
    const filtered = media.filter(item => (!search || `${item.filename} ${item.alt_text || ''} ${item.caption || ''}`.toLowerCase().includes(search.toLowerCase())) && (!type || item.mime_type === type));
    const types = [...new Set(media.map(item => item.mime_type))];
    return <SectionCard title="Media library" description="Search, filter, copy, replace, and delete unused validated images. SVG and executable uploads are not accepted."><form onSubmit={event => { event.preventDefault(); form.post(route('superadmin.website.media.store'), { forceFormData: true, onSuccess: () => form.reset() }); }} className="grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]"><input required type="file" accept="image/png,image/jpeg,image/webp,image/gif" onChange={event => form.setData('file', event.target.files[0])} className="text-sm" /><Input placeholder="Alt text" value={form.data.alt_text} onChange={event => form.setData('alt_text', event.target.value)} /><Input placeholder="Caption" value={form.data.caption} onChange={event => form.setData('caption', event.target.value)} /><Button type="submit" variant="brand" loading={form.processing}>Upload</Button></form><div className="mt-5 flex flex-wrap gap-3"><Input placeholder="Search filename, alt text, or caption" value={search} onChange={event => setSearch(event.target.value)} /><select value={type} onChange={event => setType(event.target.value)} className="rounded-lg border-slate-300 text-sm"><option value="">All image types</option>{types.map(value => <option key={value}>{value}</option>)}</select><span className="self-center text-xs text-slate-500">{filtered.length} of {media.length}</span></div><div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{filtered.map(item => <MediaCard key={item.id} item={item} />)}</div>{!filtered.length && <EmptyState icon={FileText} title="No media matches these filters" />}</SectionCard>;
}

function BlogEditor({ post = null, categories, tags }) {
    const form = useForm({
        title: post?.title || '', slug: post?.slug || '', excerpt: post?.excerpt || '', content: post?.content || '',
        status: post?.status || 'draft', scheduled_at: post?.scheduled_at || '', seo_title: post?.seo?.title || '', seo_description: post?.seo?.description || '',
        featured_image: post?.featured_image || '', canonical_url: post?.canonical_url || '', robots_index: post?.robots_index ?? true,
        category_ids: post?.categories?.map(item => item.id) || [], tag_ids: post?.tags?.map(item => item.id) || [],
    });
    const submit = (event) => { event.preventDefault(); const options = { preserveScroll: true, onSuccess: () => { if (!post) form.reset(); } }; post ? form.put(route('superadmin.website.blog.update', post.id), options) : form.post(route('superadmin.website.blog.store'), options); };
    const toggle = (key, id) => form.setData(key, form.data[key].includes(id) ? form.data[key].filter(value => value !== id) : [...form.data[key], id]);
    return <SectionCard title={post ? post.title : 'Create article'} description={post ? `/${post.slug}` : 'Draft, schedule, or publish a sanitized long-form article.'}><form onSubmit={submit} className="space-y-3"><div className="grid gap-3 sm:grid-cols-2"><Input placeholder="Title" value={form.data.title} onChange={e => form.setData('title', e.target.value)} /><Input placeholder="article-slug" value={form.data.slug} onChange={e => form.setData('slug', e.target.value)} /></div><Input placeholder="Excerpt" value={form.data.excerpt} onChange={e => form.setData('excerpt', e.target.value)} /><textarea className="min-h-40 w-full rounded-lg border-slate-300 text-sm" placeholder="Article HTML" value={form.data.content} onChange={e => form.setData('content', e.target.value)} /><div className="grid gap-3 sm:grid-cols-3"><select className="rounded-lg border-slate-300 text-sm" value={form.data.status} onChange={e => form.setData('status', e.target.value)}><option value="draft">Draft</option><option value="published">Published</option><option value="scheduled">Scheduled</option></select><Input type="datetime-local" value={form.data.scheduled_at} onChange={e => form.setData('scheduled_at', e.target.value)} /><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!form.data.robots_index} onChange={e => form.setData('robots_index', e.target.checked)} className="rounded" />Allow search indexing</label></div><div className="grid gap-3 sm:grid-cols-2"><Input placeholder="Featured image URL" value={form.data.featured_image} onChange={e => form.setData('featured_image', e.target.value)} /><Input placeholder="Canonical URL" value={form.data.canonical_url} onChange={e => form.setData('canonical_url', e.target.value)} /><Input placeholder="SEO title" value={form.data.seo_title} onChange={e => form.setData('seo_title', e.target.value)} /><Input placeholder="SEO description" value={form.data.seo_description} onChange={e => form.setData('seo_description', e.target.value)} /></div><div className="grid gap-3 sm:grid-cols-2">{[['Categories', categories, 'category_ids'], ['Tags', tags, 'tag_ids']].map(([label, items, key]) => <div key={key}><p className="text-sm font-semibold">{label}</p><div className="mt-2 flex flex-wrap gap-3">{items.map(item => <label key={item.id} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data[key].includes(item.id)} onChange={() => toggle(key, item.id)} className="rounded" />{item.name}</label>)}</div></div>)}</div>{Object.values(form.errors).map(error => <p key={error} className="text-sm text-rose-600">{error}</p>)}<div className="flex justify-end gap-2">{post && <Button type="button" variant="ghost" onClick={() => router.delete(route('superadmin.website.blog.destroy', post.id))}>Delete</Button>}<Button type="submit" variant="brand" loading={form.processing}>{post ? 'Save article' : 'Create article'}</Button></div></form></SectionCard>;
}

function TaxonomyEditor({ title, items, storeRoute, destroyRoute }) {
    const form = useForm({ name: '', slug: '' });
    return <SectionCard title={title}><form onSubmit={event => { event.preventDefault(); form.post(route(storeRoute), { preserveScroll: true, onSuccess: () => form.reset() }); }} className="flex flex-wrap gap-2"><Input placeholder="Name" value={form.data.name} onChange={event => form.setData('name', event.target.value)} /><Input placeholder="slug" value={form.data.slug} onChange={event => form.setData('slug', event.target.value)} /><Button type="submit" variant="brand">Add</Button></form><div className="mt-3 flex flex-wrap gap-2">{items.map(item => <span key={item.id} className="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-sm">{item.name}<button onClick={() => router.delete(route(destroyRoute, item.id), { preserveScroll: true })} className="text-rose-600" aria-label={`Remove ${item.name}`}>×</button></span>)}</div></SectionCard>;
}

function BlogTab({ posts, categories, tags }) { return <div className="space-y-5"><div className="grid gap-5 sm:grid-cols-2"><TaxonomyEditor title="Categories" items={categories} storeRoute="superadmin.website.categories.store" destroyRoute="superadmin.website.categories.destroy" /><TaxonomyEditor title="Tags" items={tags} storeRoute="superadmin.website.tags.store" destroyRoute="superadmin.website.tags.destroy" /></div><BlogEditor categories={categories} tags={tags} />{posts.map(post => <BlogEditor key={post.id} post={post} categories={categories} tags={tags} />)}</div>; }

function FormBuilder({ item }) {
    const form = useForm({ name: item.name, slug: item.slug, is_active: !!item.is_active, fields: item.fields || [] });
    const addField = () => form.setData('fields', [...form.data.fields, { name: `field_${form.data.fields.length + 1}`, label: 'New field', type: 'text', required: false }]);
    const updateField = (index, key, value) => form.setData('fields', form.data.fields.map((field, position) => position === index ? { ...field, [key]: value } : field));
    return <form onSubmit={event => { event.preventDefault(); form.put(route('superadmin.website.forms.update', item.id), { preserveScroll: true }); }} className="space-y-3 rounded-lg border border-slate-200 p-4"><div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]"><Input value={form.data.name} onChange={event => form.setData('name', event.target.value)} /><Input value={form.data.slug} onChange={event => form.setData('slug', event.target.value)} /><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={event => form.setData('is_active', event.target.checked)} className="rounded" />Active</label></div>{form.data.fields.map((field, index) => <div key={`${field.name}-${index}`} className="grid gap-2 sm:grid-cols-[1fr_1fr_130px_auto_auto]"><Input placeholder="Field name" value={field.name} onChange={event => updateField(index, 'name', event.target.value)} /><Input placeholder="Label" value={field.label} onChange={event => updateField(index, 'label', event.target.value)} /><select value={field.type} onChange={event => updateField(index, 'type', event.target.value)} className="rounded-lg border-slate-300 text-sm"><option value="text">Text</option><option value="email">Email</option><option value="tel">Phone</option><option value="textarea">Textarea</option></select><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!field.required} onChange={event => updateField(index, 'required', event.target.checked)} className="rounded" />Required</label><button type="button" onClick={() => form.setData('fields', form.data.fields.filter((_, position) => position !== index))} className="text-sm font-semibold text-rose-600">Remove</button></div>)}{Object.values(form.errors).map(error => <p key={error} className="text-sm text-rose-600">{error}</p>)}<div className="flex justify-between"><Button type="button" variant="secondary" onClick={addField}>Add field</Button><Button type="submit" variant="brand" loading={form.processing}>Save form</Button></div></form>;
}

function FormBuilders({ forms }) { return <SectionCard title="Form builder" description="Configure validated public fields and activate each lead form when ready."><div className="space-y-4">{forms.map(item => <FormBuilder key={item.id} item={item} />)}</div></SectionCard>; }

function LeadRow({ lead }) {
    const form = useForm({ status: lead.status || 'new', notes: lead.notes || '' });
    return <form onSubmit={event => { event.preventDefault(); form.put(route('superadmin.website.leads.update', lead.id), { preserveScroll: true }); }} className="grid gap-3 py-4 lg:grid-cols-[1fr_1.5fr_180px_1.5fr_auto]">
        <div><p className="font-semibold">{lead.name || lead.email || 'Anonymous'}</p><p className="text-xs text-slate-500">{lead.email} {lead.company ? `· ${lead.company}` : ''}</p><p className="mt-1 text-xs text-slate-400">{lead.form?.name} · {new Date(lead.created_at).toLocaleString()}</p></div>
        <p className="whitespace-pre-wrap text-sm text-slate-600">{lead.message}</p>
        <select className="h-fit rounded-lg border-slate-300 text-sm" value={form.data.status} onChange={event => form.setData('status', event.target.value)}>{['new','contacted','qualified','won','lost','spam'].map(value => <option key={value} value={value}>{value[0].toUpperCase() + value.slice(1)}</option>)}</select>
        <textarea rows="3" className="rounded-lg border-slate-300 text-sm" placeholder="Private notes" value={form.data.notes} onChange={event => form.setData('notes', event.target.value)} />
        <Button type="submit" variant="secondary" size="sm" loading={form.processing}>Save</Button>
    </form>;
}

function LeadsTab({ forms, leads }) {
    const form = useForm({ name: '', slug: '' });
    return <div className="space-y-5"><SectionCard title="Lead forms" description="New forms start with safe name, email, company, and message fields."><form onSubmit={e => { e.preventDefault(); form.post(route('superadmin.website.forms.store'), { onSuccess: () => form.reset() }); }} className="flex flex-wrap gap-3"><Input placeholder="Form name" value={form.data.name} onChange={e => form.setData('name', e.target.value)} /><Input placeholder="form-slug" value={form.data.slug} onChange={e => form.setData('slug', e.target.value)} /><Button type="submit" variant="brand">Create</Button></form><div className="mt-4 divide-y">{forms.map(item => <div key={item.id} className="flex justify-between py-3 text-sm"><span><b>{item.name}</b> · {item.slug}</span><span>{item.submissions_count} submissions</span></div>)}</div></SectionCard><SectionCard title="Leads" description="Captured fields, attribution, lifecycle, private notes, and spreadsheet-safe CSV export." actions={<Button href={route('superadmin.website.leads.export')} variant="secondary">Export CSV</Button>}><div className="divide-y">{leads.map(lead => <LeadRow key={lead.id} lead={lead} />)}</div>{!leads.length && <EmptyState icon={FileText} title="No leads yet" />}</SectionCard></div>;
}

function OverviewTab({ pages, posts, media, forms, leads, redirects, onTab }) {
    const home = pages.find(page => page.slug === 'home');
    const metrics = [
        ['Published pages', pages.filter(page => page.status === 'published').length],
        ['Draft pages', pages.filter(page => page.status === 'draft' || page.status === 'scheduled').length],
        ['Blog posts', posts.length], ['Media files', media.length], ['Forms', forms.length],
        ['New leads', leads.filter(lead => lead.status === 'new').length], ['Redirects', redirects.length],
    ];
    return <div className="space-y-5"><div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{metrics.map(([label, value]) => <div key={label} className="rounded-xl border border-slate-200 bg-white p-5"><p className="text-sm text-slate-500">{label}</p><p className="mt-2 text-3xl font-bold text-slate-950">{value}</p></div>)}</div><SectionCard title="Quick actions" description="Common website publishing tasks."><div className="flex flex-wrap gap-3"><Button href={route('superadmin.website.pages.create')} variant="brand">Create page</Button><Button type="button" variant="secondary" onClick={() => onTab('blog')}>Create blog post</Button><Button type="button" variant="secondary" onClick={() => onTab('media')}>Upload media</Button>{home && <Button href={route('superadmin.website.pages.edit', home.id)} variant="secondary">Edit homepage</Button>}<a href="/" target="_blank" rel="noopener noreferrer" className="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">View website</a></div></SectionCard></div>;
}

export default function Index({ pages, navigation, footerLinks, settings, media = [], redirects = [], blogPosts = [], categories = [], tags = [], forms = [], leads = [] }) {
    const [tab, setTab] = useState('overview');
    const tabs = [
        { value: 'overview', label: 'Overview' },
        { value: 'pages', label: 'Pages' },
        { value: 'navigation', label: 'Navigation' },
        { value: 'footer', label: 'Footer' },
        { value: 'settings', label: 'Branding' },
        { value: 'media', label: 'Media' },
        { value: 'redirects', label: 'Redirects' },
        { value: 'blog', label: 'Blog' },
        { value: 'leads', label: 'Forms & leads' },
        { value: 'form_builder', label: 'Form builder' },
    ];

    return (
        <AuthenticatedLayout header={<PageHeader title="Website" subtitle="Manage your public marketing site: pages, navigation, footer, and branding." />}>
            <Head title="Website" />

            <div className="space-y-6">
                <Tabs items={tabs} active={tab} onChange={setTab} />

                {tab === 'overview' && <OverviewTab pages={pages} posts={blogPosts} media={media} forms={forms} leads={leads} redirects={redirects} onTab={setTab} />}

                {tab === 'pages' && <PagesTab pages={pages} />}

                {tab === 'navigation' && (
                    <LinkListEditor
                        title="Navigation"
                        description="Create header, mobile, and footer items. Drag items to change their display order; dropdown children must share their parent's menu group."
                        items={navigation}
                        fields={[
                            { key: 'label', label: 'Label' },
                            { key: 'type', label: 'Type', type: 'select', default: 'external', options: [{ value: 'internal', label: 'Internal page' }, { value: 'external', label: 'External URL' }, { value: 'dropdown', label: 'Dropdown' }, { value: 'button', label: 'Button' }] },
                            { key: 'website_page_id', label: 'Internal page', type: 'select', options: [{ value: '', label: 'No internal page' }, ...pages.map(page => ({ value: page.id, label: page.title }))] },
                            { key: 'url', label: 'URL (external/button)' },
                            { key: 'menu_group', label: 'Menu group', type: 'select', default: 'header', options: [{ value: 'header', label: 'Header' }, { value: 'mobile', label: 'Mobile' }, { value: 'footer', label: 'Footer' }] },
                            { key: 'parent_id', label: 'Parent dropdown', type: 'select', options: [{ value: '', label: 'No parent' }, ...navigation.filter(item => item.type === 'dropdown').map(item => ({ value: item.id, label: item.label }))] },
                            { key: 'style', label: 'Style', type: 'select', default: 'link', options: [{ value: 'link', label: 'Link' }, { value: 'button', label: 'Button' }] },
                            { key: 'open_new_tab', label: 'Open in new tab', type: 'checkbox', default: false },
                            { key: 'is_active', label: 'Active', type: 'checkbox', default: true },
                        ]}
                        storeRoute="superadmin.website.navigation.store"
                        updateRouteName="superadmin.website.navigation.update"
                        destroyRouteName="superadmin.website.navigation.destroy"
                        reorderRouteName="superadmin.website.navigation.order"
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
                        reorderRouteName="superadmin.website.footer-links.order"
                    />
                )}

                {tab === 'settings' && <SettingsTab settings={settings} />}
                {tab === 'media' && <MediaTab media={media} />}
                {tab === 'redirects' && <RedirectsTab redirects={redirects} />}
                {tab === 'blog' && <BlogTab posts={blogPosts} categories={categories} tags={tags} />}
                {tab === 'leads' && <LeadsTab forms={forms} leads={leads} />}
                {tab === 'form_builder' && <FormBuilders forms={forms} />}
            </div>
        </AuthenticatedLayout>
    );
}
