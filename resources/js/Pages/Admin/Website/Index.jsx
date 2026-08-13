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
import { ExternalLink, FileText, GripVertical, Plus } from 'lucide-react';
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

function SettingsTab({ settings }) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm(settings);

    const submit = (event) => {
        event.preventDefault();
        put(route('superadmin.website.settings.update'), { preserveScroll: true });
    };

    const fields = [
        { key: 'site_name', label: 'Site name' },
        { key: 'logo_url', label: 'Logo URL' },
        { key: 'logo_dark_url', label: 'Dark logo URL' },
        { key: 'favicon_url', label: 'Favicon URL' },
        { key: 'primary_color', label: 'Primary color (hex)' },
        { key: 'secondary_color', label: 'Secondary color (hex)' },
        { key: 'accent_color', label: 'Accent color (hex)' },
        { key: 'footer_description', label: 'Footer description' },
        { key: 'copyright_text', label: 'Copyright text (year is added automatically)' },
        { key: 'heading_font', label: 'Heading font', type: 'select', options: ['', 'Inter', 'Manrope', 'Poppins', 'Roboto', 'system-ui'].map(value => ({ value, label: value || 'Default heading font' })) },
        { key: 'body_font', label: 'Body font', type: 'select', options: ['', 'Inter', 'Manrope', 'Poppins', 'Roboto', 'system-ui'].map(value => ({ value, label: value || 'Default body font' })) },
        { key: 'button_radius', label: 'Button radius', type: 'select', options: ['', '0', '4px', '8px', '12px', '9999px'].map(value => ({ value, label: value || 'Default button radius' })) },
        { key: 'card_radius', label: 'Card radius', type: 'select', options: ['', '0', '8px', '12px', '16px', '24px'].map(value => ({ value, label: value || 'Default card radius' })) },
        { key: 'container_width', label: 'Container width', type: 'select', options: ['', '1024px', '1152px', '1280px', '1440px'].map(value => ({ value, label: value || 'Default container width' })) },
        { key: 'contact_email', label: 'Contact email' },
        { key: 'social_twitter', label: 'Twitter/X URL' },
        { key: 'social_linkedin', label: 'LinkedIn URL' },
        { key: 'default_meta_title_format', label: 'Default title format' },
        { key: 'default_description', label: 'Default meta description' },
        { key: 'default_og_image', label: 'Default Open Graph image' },
        { key: 'twitter_card_type', label: 'Twitter card type', type: 'select', options: [{ value: '', label: 'Default (large image)' }, { value: 'summary', label: 'Summary' }, { value: 'summary_large_image', label: 'Summary with large image' }] },
        { key: 'canonical_base_url', label: 'Canonical base URL' },
        { key: 'google_verification', label: 'Google verification' },
        { key: 'bing_verification', label: 'Bing verification' },
        { key: 'google_analytics_id', label: 'Google Analytics ID' },
        { key: 'google_tag_manager_id', label: 'Google Tag Manager ID' },
        { key: 'meta_pixel_id', label: 'Meta Pixel ID' },
    ];

    return (
        <SectionCard title="Site settings" description="Used by the public site's header, footer, and metadata.">
            <form onSubmit={submit}>
                <div className="grid gap-5 md:grid-cols-2">
                    {fields.map((field) => (
                        <FormField key={field.key} id={field.key} label={field.label} error={errors[field.key]}>
                            <FieldControl field={field} value={data[field.key]} onChange={(value) => setData(field.key, value)} />
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
