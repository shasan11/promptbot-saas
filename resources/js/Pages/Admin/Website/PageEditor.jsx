import PageHeader from '@/Components/Superadmin/PageHeader';
import Alert from '@/Components/UI/Alert';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, ExternalLink, Trash2 } from 'lucide-react';
import { useState } from 'react';

function DetailsForm({ page }) {
    const { data, setData, post, put, processing, errors, isDirty } = useForm({
        title: page?.title || '',
        slug: page?.slug || '',
        status: page?.status || 'draft',
        seo_title: page?.seo?.title || '',
        seo_description: page?.seo?.description || '',
    });

    const submit = (event) => {
        event.preventDefault();
        page ? put(route('superadmin.website.pages.update', page.id)) : post(route('superadmin.website.pages.store'));
    };

    return (
        <form onSubmit={submit}>
            <SectionCard title="Page details">
                <div className="grid gap-5 md:grid-cols-2">
                    <FormField id="title" label="Title" required error={errors.title}>
                        <Input id="title" value={data.title} error={!!errors.title} onChange={(event) => setData('title', event.target.value)} />
                    </FormField>
                    <FormField id="slug" label="Slug" required error={errors.slug} hint={data.slug === 'home' ? 'Renders at the site root.' : undefined}>
                        <Input id="slug" value={data.slug} error={!!errors.slug} onChange={(event) => setData('slug', event.target.value)} placeholder="home" />
                    </FormField>
                    <FormField id="status" label="Status" error={errors.status}>
                        <Select id="status" value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </Select>
                    </FormField>
                    <div />
                    <FormField id="seo_title" label="SEO title" optional error={errors.seo_title} hint={`${data.seo_title.length}/60 characters`}>
                        <Input id="seo_title" maxLength={70} value={data.seo_title} onChange={(event) => setData('seo_title', event.target.value)} />
                    </FormField>
                    <FormField id="seo_description" label="SEO description" optional error={errors.seo_description} hint={`${data.seo_description.length}/160 characters`}>
                        <Input id="seo_description" maxLength={180} value={data.seo_description} onChange={(event) => setData('seo_description', event.target.value)} />
                    </FormField>
                </div>

                {(data.seo_title || data.seo_description) && (
                    <div className="mt-5 rounded-md border border-slate-200 bg-slate-50 p-4">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Search result preview</p>
                        <p className="truncate text-sm text-blue-800">{data.seo_title || data.title || 'Untitled page'}</p>
                        <p className="text-xs text-brand-700">yoursite.com/{data.slug === 'home' ? '' : data.slug}</p>
                        <p className="mt-1 line-clamp-2 text-xs text-slate-600">{data.seo_description || 'No description set yet.'}</p>
                    </div>
                )}
            </SectionCard>
            <div className="mt-4 flex items-center justify-end gap-3">
                {isDirty && <span className="text-xs font-medium text-amber-700">Unsaved changes</span>}
                <Button type="submit" variant="brand" loading={processing}>{page ? 'Save details' : 'Create page'}</Button>
            </div>
        </form>
    );
}

const emptyContent = (type) => {
    if (type === 'hero') return { heading: '', subheading: '', image_url: '', button_label: '', button_url: '' };
    if (type === 'cta') return { heading: '', button_label: '', button_url: '' };

    return { html: '' };
};

function SectionFields({ section, onChange }) {
    const content = section.content || {};
    const set = (key, value) => onChange({ ...section, content: { ...content, [key]: value } });

    if (section.type === 'rich_text') {
        return (
            <FormField label="HTML content">
                <Textarea className="min-h-32 font-mono" value={content.html || ''} onChange={(event) => set('html', event.target.value)} />
            </FormField>
        );
    }

    if (section.type === 'hero') {
        return (
            <div className="grid gap-4 md:grid-cols-2">
                <FormField label="Heading"><Input value={content.heading || ''} onChange={(event) => set('heading', event.target.value)} /></FormField>
                <FormField label="Subheading"><Input value={content.subheading || ''} onChange={(event) => set('subheading', event.target.value)} /></FormField>
                <FormField label="Image URL" className="md:col-span-2"><Input value={content.image_url || ''} onChange={(event) => set('image_url', event.target.value)} /></FormField>
                <FormField label="Button label"><Input value={content.button_label || ''} onChange={(event) => set('button_label', event.target.value)} /></FormField>
                <FormField label="Button URL"><Input value={content.button_url || ''} onChange={(event) => set('button_url', event.target.value)} /></FormField>
            </div>
        );
    }

    return (
        <div className="grid gap-4 md:grid-cols-2">
            <FormField label="Heading" className="md:col-span-2"><Input value={content.heading || ''} onChange={(event) => set('heading', event.target.value)} /></FormField>
            <FormField label="Button label"><Input value={content.button_label || ''} onChange={(event) => set('button_label', event.target.value)} /></FormField>
            <FormField label="Button URL"><Input value={content.button_url || ''} onChange={(event) => set('button_url', event.target.value)} /></FormField>
        </div>
    );
}

function SectionPreview({ section }) {
    const content = section.content || {};

    if (section.type === 'hero') {
        return (
            <div className="rounded-md bg-navy-900 p-6 text-center text-white">
                <h3 className="text-lg font-bold">{content.heading || 'Hero heading'}</h3>
                {content.subheading && <p className="mt-1 text-sm text-slate-300">{content.subheading}</p>}
                {content.button_label && <span className="mt-3 inline-block rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold">{content.button_label}</span>}
            </div>
        );
    }

    if (section.type === 'cta') {
        return (
            <div className="flex items-center justify-between rounded-md bg-brand-50 p-4">
                <span className="text-sm font-semibold text-brand-900">{content.heading || 'Call to action heading'}</span>
                {content.button_label && <span className="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">{content.button_label}</span>}
            </div>
        );
    }

    return <div className="prose prose-sm max-w-none rounded-md bg-white p-4" dangerouslySetInnerHTML={{ __html: content.html || '<p class="text-slate-400">Empty rich text block</p>' }} />;
}

function SectionsEditor({ page, sectionTypes }) {
    const [sections, setSections] = useState(page.sections?.map((section) => ({ type: section.type, content: section.content || {} })) || []);
    const [collapsed, setCollapsed] = useState({});
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);

    const mutate = (next) => { setSections(next); setDirty(true); };
    const addSection = (type) => mutate([...sections, { type, content: emptyContent(type) }]);
    const removeSection = (index) => mutate(sections.filter((_, sectionIndex) => sectionIndex !== index));
    const updateSection = (index, next) => mutate(sections.map((section, sectionIndex) => (sectionIndex === index ? next : section)));
    const move = (index, direction) => {
        const next = [...sections];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        mutate(next);
    };

    const save = () => {
        setSaving(true);
        router.put(route('superadmin.website.pages.sections', page.id), { sections }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
            onSuccess: () => setDirty(false),
        });
    };

    return (
        <div className="mt-6 grid gap-6 xl:grid-cols-2">
            <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-soft">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold text-slate-900">Sections</h2>
                        <p className="mt-1 text-xs text-slate-500">Rendered top to bottom on the public page.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {sectionTypes.map((type) => (
                            <Button key={type} variant="secondary" size="sm" onClick={() => addSection(type)}>+ {type.replace('_', ' ')}</Button>
                        ))}
                    </div>
                </div>

                <div className="space-y-3">
                    {sections.map((section, index) => {
                        const isCollapsed = collapsed[index];
                        return (
                            <div key={index} className="rounded-md border border-slate-200 bg-slate-50">
                                <div className="flex items-center justify-between gap-2 p-3">
                                    <button type="button" onClick={() => setCollapsed((state) => ({ ...state, [index]: !state[index] }))} className="flex items-center gap-2">
                                        {isCollapsed ? <ChevronDown className="h-4 w-4 text-slate-400" /> : <ChevronUp className="h-4 w-4 text-slate-400" />}
                                        <Badge tone="neutral">{section.type.replace('_', ' ')}</Badge>
                                    </button>
                                    <div className="flex items-center gap-1">
                                        <Button variant="ghost" size="sm" onClick={() => move(index, -1)} disabled={index === 0} aria-label="Move up">Up</Button>
                                        <Button variant="ghost" size="sm" onClick={() => move(index, 1)} disabled={index === sections.length - 1} aria-label="Move down">Down</Button>
                                        <Button variant="ghost" size="sm" icon={Trash2} onClick={() => removeSection(index)} aria-label="Remove section" />
                                    </div>
                                </div>
                                {!isCollapsed && <div className="border-t border-slate-200 p-4"><SectionFields section={section} onChange={(next) => updateSection(index, next)} /></div>}
                            </div>
                        );
                    })}
                    {!sections.length && <p className="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">No sections yet. Add one above.</p>}
                </div>

                <div className="mt-6 flex items-center justify-end gap-3">
                    {dirty && <span className="text-xs font-medium text-amber-700">Unsaved changes</span>}
                    <Button type="button" variant="brand" loading={saving} onClick={save}>Save sections</Button>
                </div>
            </section>

            <aside className="xl:sticky xl:top-20 xl:h-fit">
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Live preview</p>
                <div className="space-y-3 rounded-lg border border-slate-200 bg-slate-100 p-4">
                    {sections.length ? sections.map((section, index) => <SectionPreview key={index} section={section} />) : <p className="py-10 text-center text-sm text-slate-400">Nothing to preview yet.</p>}
                </div>
            </aside>
        </div>
    );
}

export default function PageEditor({ page, sectionTypes }) {
    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={page ? `Edit: ${page.title}` : 'Create page'}
                    subtitle="Page details and content sections."
                    actions={(
                        <div className="flex gap-2">
                            {page?.status === 'published' && (
                                <Button href={`/${page.slug === 'home' ? '' : page.slug}`} variant="secondary" icon={ExternalLink}>View public page</Button>
                            )}
                            <Button href={route('superadmin.website.index')} variant="secondary">Back to website</Button>
                        </div>
                    )}
                />
            )}
        >
            <Head title={page ? `Edit ${page.title}` : 'Create page'} />

            {page && page.status === 'draft' && (
                <Alert tone="info" className="mb-6">This page is a draft and won't appear on the public site until you publish it.</Alert>
            )}

            <DetailsForm page={page} />
            {page && <SectionsEditor page={page} sectionTypes={sectionTypes} />}
        </AuthenticatedLayout>
    );
}
