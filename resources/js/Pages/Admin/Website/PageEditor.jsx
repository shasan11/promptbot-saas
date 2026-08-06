import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950';

function Field({ label, error, children, className = '' }) {
    return (
        <label className={`block ${className}`}>
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            <div className="mt-2">{children}</div>
            {error && <p className="mt-1 text-xs font-semibold text-rose-600">{error}</p>}
        </label>
    );
}

function Panel({ title, subtitle, children }) {
    return (
        <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-6">
                <h2 className="text-base font-bold text-slate-950">{title}</h2>
                {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
            </div>
            <div className="grid gap-5 md:grid-cols-2">{children}</div>
        </section>
    );
}

function DetailsForm({ page }) {
    const { data, setData, post, put, processing, errors } = useForm({
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
            <Panel title="Page details">
                <Field label="Title" error={errors.title}>
                    <input className={inputClass} value={data.title} onChange={(event) => setData('title', event.target.value)} />
                </Field>
                <Field label="Slug" error={errors.slug}>
                    <input className={inputClass} value={data.slug} onChange={(event) => setData('slug', event.target.value)} placeholder="home" />
                </Field>
                <Field label="Status" error={errors.status}>
                    <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </Field>
                <div />
                <Field label="SEO title" error={errors.seo_title}>
                    <input className={inputClass} value={data.seo_title} onChange={(event) => setData('seo_title', event.target.value)} />
                </Field>
                <Field label="SEO description" error={errors.seo_description}>
                    <input className={inputClass} value={data.seo_description} onChange={(event) => setData('seo_description', event.target.value)} />
                </Field>
            </Panel>
            <div className="mt-4 flex justify-end">
                <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    {processing ? 'Saving...' : page ? 'Save details' : 'Create page'}
                </button>
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
            <Field label="HTML content">
                <textarea className={`${inputClass} min-h-32 font-mono`} value={content.html || ''} onChange={(event) => set('html', event.target.value)} />
            </Field>
        );
    }

    if (section.type === 'hero') {
        return (
            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Heading"><input className={inputClass} value={content.heading || ''} onChange={(event) => set('heading', event.target.value)} /></Field>
                <Field label="Subheading"><input className={inputClass} value={content.subheading || ''} onChange={(event) => set('subheading', event.target.value)} /></Field>
                <Field label="Image URL"><input className={inputClass} value={content.image_url || ''} onChange={(event) => set('image_url', event.target.value)} /></Field>
                <Field label="Button label"><input className={inputClass} value={content.button_label || ''} onChange={(event) => set('button_label', event.target.value)} /></Field>
                <Field label="Button URL"><input className={inputClass} value={content.button_url || ''} onChange={(event) => set('button_url', event.target.value)} /></Field>
            </div>
        );
    }

    return (
        <div className="grid gap-4 md:grid-cols-2">
            <Field label="Heading"><input className={inputClass} value={content.heading || ''} onChange={(event) => set('heading', event.target.value)} /></Field>
            <Field label="Button label"><input className={inputClass} value={content.button_label || ''} onChange={(event) => set('button_label', event.target.value)} /></Field>
            <Field label="Button URL"><input className={inputClass} value={content.button_url || ''} onChange={(event) => set('button_url', event.target.value)} /></Field>
        </div>
    );
}

function SectionsEditor({ page, sectionTypes }) {
    const [sections, setSections] = useState(page.sections?.map((section) => ({ type: section.type, content: section.content || {} })) || []);
    const [saving, setSaving] = useState(false);

    const addSection = (type) => setSections([...sections, { type, content: emptyContent(type) }]);
    const removeSection = (index) => setSections(sections.filter((_, sectionIndex) => sectionIndex !== index));
    const updateSection = (index, next) => setSections(sections.map((section, sectionIndex) => (sectionIndex === index ? next : section)));
    const move = (index, direction) => {
        const next = [...sections];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        setSections(next);
    };

    const save = () => {
        setSaving(true);
        router.put(route('superadmin.website.pages.sections', page.id), { sections }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <section className="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-5 flex items-center justify-between">
                <div>
                    <h2 className="text-base font-bold text-slate-950">Sections</h2>
                    <p className="mt-1 text-sm text-slate-500">Rendered top to bottom on the public page.</p>
                </div>
                <div className="flex gap-2">
                    {sectionTypes.map((type) => (
                        <button key={type} type="button" onClick={() => addSection(type)} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            + {type.replace('_', ' ')}
                        </button>
                    ))}
                </div>
            </div>

            <div className="space-y-4">
                {sections.map((section, index) => (
                    <div key={index} className="rounded-md border border-slate-200 bg-slate-50 p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <span className="rounded-full bg-slate-950 px-2.5 py-1 text-xs font-bold uppercase text-white">{section.type.replace('_', ' ')}</span>
                            <div className="flex gap-2">
                                <button type="button" onClick={() => move(index, -1)} className="text-xs font-semibold text-slate-600 hover:text-slate-950">Up</button>
                                <button type="button" onClick={() => move(index, 1)} className="text-xs font-semibold text-slate-600 hover:text-slate-950">Down</button>
                                <button type="button" onClick={() => removeSection(index)} className="text-xs font-semibold text-rose-600 hover:text-rose-800">Remove</button>
                            </div>
                        </div>
                        <SectionFields section={section} onChange={(next) => updateSection(index, next)} />
                    </div>
                ))}
                {!sections.length && <p className="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">No sections yet. Add one above.</p>}
            </div>

            <div className="mt-6 flex justify-end">
                <button type="button" disabled={saving} onClick={save} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    {saving ? 'Saving...' : 'Save sections'}
                </button>
            </div>
        </section>
    );
}

export default function PageEditor({ page, sectionTypes }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={page ? `Edit: ${page.title}` : 'Create Page'}
                    subtitle="Page details and content sections."
                    actions={<Link href={route('superadmin.website.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to website</Link>}
                />
            }
        >
            <Head title={page ? `Edit ${page.title}` : 'Create Page'} />
            <div className="mx-auto max-w-4xl">
                <DetailsForm page={page} />
                {page && <SectionsEditor page={page} sectionTypes={sectionTypes} />}
            </div>
        </AuthenticatedLayout>
    );
}
