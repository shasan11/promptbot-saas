import PageHeader from '@/Components/Superadmin/PageHeader';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Copy, ExternalLink, Eye, EyeOff, GripVertical, Trash2 } from 'lucide-react';
import { useState } from 'react';

const inputClass = 'mt-1.5 w-full rounded-lg border-slate-300 text-sm';
const itemSchemas = {
    logo_cloud: ['name', 'image_url', 'url'],
    feature_grid: ['icon', 'title', 'description', 'url'],
    feature_list: ['title', 'description'],
    feature_showcase: ['title', 'description'],
    stats: ['value', 'label'],
    testimonials: ['quote', 'name', 'role', 'company', 'avatar_url', 'logo_url'],
    integrations: ['name', 'description', 'image_url', 'url'],
    faq: ['question', 'answer'],
    gallery: ['image_url', 'alt_text'],
    how_it_works: ['title', 'description'],
    pricing: ['name', 'description', 'monthly_price', 'annual_price', 'currency', 'url'],
};
const itemCollectionKeys = { how_it_works: 'steps', comparison_table: 'rows' };

function PageDetails({ page }) {
    const { media = [] } = usePage().props;
    const form = useForm({
        title: page?.title || '', slug: page?.slug || '', page_type: page?.page_type || 'standard',
        template: page?.template || 'default', status: page?.status || 'draft', scheduled_at: page?.scheduled_at || '',
        seo_title: page?.seo?.title || '', seo_description: page?.seo?.description || '',
        canonical_url: page?.canonical_url || '', robots_index: page?.robots_index ?? true, robots_follow: page?.robots_follow ?? true,
        og_title: page?.open_graph?.title || '', og_description: page?.open_graph?.description || '', og_image: page?.open_graph?.image || '',
        twitter_title: page?.twitter?.title || '', twitter_description: page?.twitter?.description || '', twitter_image: page?.twitter?.image || '',
        schema_json: page?.schema_json ? JSON.stringify(page.schema_json, null, 2) : '', create_slug_redirect: true,
    });
    const [schemaError, setSchemaError] = useState('');
    const submit = (event) => {
        event.preventDefault();
        let schema = null;
        try { schema = form.data.schema_json.trim() ? JSON.parse(form.data.schema_json) : null; if (schema !== null && typeof schema !== 'object') throw new Error(); setSchemaError(''); }
        catch { setSchemaError('Structured data must be valid JSON.'); return; }
        form.transform((data) => ({ ...data, schema_json: schema }));
        page ? form.put(route('superadmin.website.pages.update', page.id)) : form.post(route('superadmin.website.pages.store'));
    };
    const field = (key, label, type = 'text') => <label className="text-sm font-medium text-slate-700">{label}<input type={type} list={key.includes('image') ? 'cms-media-urls' : undefined} value={form.data[key] || ''} onChange={(event) => form.setData(key, event.target.value)} className={inputClass} />{form.errors[key] && <span className="text-xs text-rose-600">{form.errors[key]}</span>}</label>;
    return <form onSubmit={submit} className="space-y-6"><datalist id="cms-media-urls">{media.map(item => <option key={item.id} value={item.url}>{item.filename}</option>)}</datalist>
        <SectionCard title="Page details"><div className="grid gap-4 md:grid-cols-2">
            {field('title', 'Title')}{field('slug', 'Slug')}
            <label className="text-sm font-medium">Page type<select value={form.data.page_type} onChange={(event) => form.setData('page_type', event.target.value)} className={inputClass}>{['standard','home','pricing','features','integrations','about','contact','legal','custom'].map((value) => <option key={value}>{value}</option>)}</select></label>
            <label className="text-sm font-medium">Status<select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} className={inputClass}>{['draft','scheduled','published','archived'].map((value) => <option key={value}>{value}</option>)}</select></label>
            {form.data.status === 'scheduled' && field('scheduled_at', 'Publish at', 'datetime-local')}
        </div></SectionCard>
        <SectionCard title="Search and social metadata" description="Control search snippets, canonical URL, robots, Open Graph, Twitter, and structured data."><div className="grid gap-4 md:grid-cols-2">
            {field('seo_title','SEO title')}{field('seo_description','Meta description')}{field('canonical_url','Canonical URL')}
            <div className="flex items-center gap-5 pt-7 text-sm"><label><input type="checkbox" className="mr-2 rounded" checked={form.data.robots_index} onChange={(event) => form.setData('robots_index', event.target.checked)} />Index</label><label><input type="checkbox" className="mr-2 rounded" checked={form.data.robots_follow} onChange={(event) => form.setData('robots_follow', event.target.checked)} />Follow</label></div>
            {field('og_title','Open Graph title')}{field('og_description','Open Graph description')}{field('og_image','Open Graph image URL')}
            {field('twitter_title','Twitter title')}{field('twitter_description','Twitter description')}{field('twitter_image','Twitter image URL')}
        </div><label className="mt-4 block text-sm font-medium text-slate-700">Structured data (JSON-LD)<textarea rows="7" className={`${inputClass} font-mono`} value={form.data.schema_json} onChange={(event) => form.setData('schema_json', event.target.value)} placeholder={'{"@context":"https://schema.org","@type":"WebPage"}'} /></label>{(schemaError || form.errors.schema_json) && <p className="mt-1 text-xs text-rose-600">{schemaError || form.errors.schema_json}</p>}<div className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4"><p className="text-xs font-semibold uppercase text-slate-500">SERP preview</p><p className="mt-2 text-lg text-blue-800">{form.data.seo_title || form.data.title || 'Untitled page'}</p><p className="text-sm text-emerald-700">yoursite.com/{form.data.slug === 'home' ? '' : form.data.slug}</p><p className="mt-1 text-sm text-slate-600">{form.data.seo_description || 'Add a concise page description.'}</p></div>{page && <label className="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" checked={!!form.data.create_slug_redirect} onChange={(event) => form.setData('create_slug_redirect', event.target.checked)} className="rounded" />Create a 301 redirect automatically if this published page's slug changes</label>}</SectionCard>
        <div className="flex justify-end"><Button type="submit" variant="brand" loading={form.processing}>{page ? 'Save details' : 'Create page'}</Button></div>
    </form>;
}

function ItemsField({ block, setContent }) {
    const comparisonKeys = block.type === 'comparison_table'
        ? ['feature', ...(block.content.columns || []).map(column => column.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, ''))]
        : null;
    const schema = comparisonKeys || itemSchemas[block.type];
    const collectionKey = itemCollectionKeys[block.type] || 'items';
    if (!schema || !Array.isArray(block.content[collectionKey])) return null;
    const items = block.content[collectionKey];
    const update = (index, key, value) => setContent(collectionKey, items.map((item, itemIndex) => itemIndex === index ? { ...item, [key]: value } : item));
    return <div className="md:col-span-2"><div className="mb-2 flex items-center justify-between"><p className="text-sm font-semibold capitalize">{collectionKey.replaceAll('_', ' ')}</p><button type="button" onClick={() => setContent(collectionKey, [...items, Object.fromEntries(schema.map((key) => [key, '']))])} className="text-sm font-semibold text-indigo-600">+ Add item</button></div><div className="space-y-3">{items.map((item, index) => <div key={index} className="grid gap-3 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-2">{schema.map((key) => <label key={key} className="text-xs font-semibold capitalize text-slate-600">{key.replaceAll('_',' ')}{['description','quote','answer'].includes(key) ? <textarea rows="2" className={inputClass} value={item[key] || ''} onChange={(event) => update(index,key,event.target.value)} /> : <input list={key.includes('image') || key.includes('avatar') || key.includes('logo') ? 'cms-media-urls' : undefined} className={inputClass} value={item[key] || ''} onChange={(event) => update(index,key,event.target.value)} />}</label>)}<button type="button" onClick={() => setContent(collectionKey, items.filter((_, itemIndex) => itemIndex !== index))} className="text-left text-xs font-semibold text-rose-600">Remove item</button></div>)}</div></div>;
}

function BlockFields({ block, onChange }) {
    const setContent = (key, value) => onChange({ ...block, content: { ...block.content, [key]: value } });
    const scalars = Object.entries(block.content).filter(([, value]) => !Array.isArray(value) && value !== null);
    return <div className="grid gap-4 md:grid-cols-2">{scalars.map(([key, value]) => <label key={key} className={['description','html'].includes(key) ? 'text-sm font-medium capitalize md:col-span-2' : 'text-sm font-medium capitalize'}>{key.replaceAll('_',' ')}{typeof value === 'boolean' ? <span className="mt-3 flex items-center gap-2"><input type="checkbox" checked={value} onChange={(event) => setContent(key,event.target.checked)} className="rounded" />Enabled</span> : key === 'data_source' ? <select className={inputClass} value={value} onChange={(event) => setContent(key,event.target.value)}><option value="live_plans">Live plans</option><option value="manual">Manual</option></select> : ['description','html'].includes(key) ? <textarea rows={key === 'html' ? 7 : 3} className={key === 'html' ? inputClass + ' font-mono' : inputClass} value={value} onChange={(event) => setContent(key,event.target.value)} /> : <input list={key.includes('image') || key.includes('poster') ? 'cms-media-urls' : undefined} className={inputClass} value={value} onChange={(event) => setContent(key,event.target.value)} />}</label>)}{block.type === 'comparison_table' && <label className="text-sm font-medium md:col-span-2">Column labels (comma separated)<Input value={(block.content.columns || []).join(', ')} onChange={(event) => setContent('columns', event.target.value.split(',').map(value => value.trim()).filter(Boolean))} /></label>}<ItemsField block={block} setContent={setContent} /></div>;
}

function BlocksEditor({ page, blockDefinitions }) {
    const { auth } = usePage().props;
    const available = blockDefinitions.filter((definition) => !definition.permission || auth.permissions.includes(definition.permission));
    const [blocks, setBlocks] = useState(page.sections.map((section) => ({ type: section.type, content: section.content || {}, is_hidden: section.is_hidden || false })));
    const [saving, setSaving] = useState(false);
    const [draggedIndex, setDraggedIndex] = useState(null);
    const definition = (type) => available.find((item) => item.key === type) || blockDefinitions.find((item) => item.key === type);
    const add = (item) => setBlocks([...blocks, { type: item.key, content: structuredClone(item.defaults), is_hidden: false }]);
    const move = (index, offset) => {
        const target = index + offset;
        if (target < 0 || target >= blocks.length) return;
        const next = [...blocks];
        [next[index], next[target]] = [next[target], next[index]];
        setBlocks(next);
    };
    const save = () => {
        setSaving(true);
        router.put(route('superadmin.website.pages.sections', page.id), { sections: blocks }, { preserveScroll: true, onFinish: () => setSaving(false) });
    };
    return <div className="mt-6 grid gap-6 xl:grid-cols-[260px_1fr]">
        <aside className="h-fit rounded-lg border border-slate-200 bg-white p-4 xl:sticky xl:top-20"><h2 className="font-semibold">Add blocks</h2><div className="mt-3 space-y-1">{available.map((item) => <button key={item.key} type="button" onClick={() => add(item)} className="flex w-full justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-slate-50"><span>{item.label}</span><span className="text-xs text-slate-400">{item.category}</span></button>)}</div></aside>
        <section className="space-y-4">{blocks.map((block,index) => <div key={index} onDragOver={(event) => event.preventDefault()} onDrop={() => { if (draggedIndex === null || draggedIndex === index) return; const next = [...blocks]; const [dragged] = next.splice(draggedIndex, 1); next.splice(index, 0, dragged); setBlocks(next); setDraggedIndex(null); }} className={`${block.is_hidden ? 'opacity-60' : ''} rounded-lg border bg-white ${draggedIndex === index ? 'ring-2 ring-indigo-300' : ''}`}><header className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3"><div className="flex items-center gap-2"><span draggable onDragStart={() => setDraggedIndex(index)} onDragEnd={() => setDraggedIndex(null)} title="Drag to reorder" className="cursor-grab text-slate-400"><GripVertical className="h-4 w-4" /></span><div><p className="font-semibold">{definition(block.type)?.label || block.type}</p><p className="text-xs text-slate-500">Position {index + 1}</p></div></div><div className="flex items-center gap-1"><button type="button" onClick={() => move(index,-1)} disabled={!index} className="rounded px-2 py-1 text-xs font-semibold disabled:opacity-30">Up</button><button type="button" onClick={() => move(index,1)} disabled={index===blocks.length-1} className="rounded px-2 py-1 text-xs font-semibold disabled:opacity-30">Down</button><button type="button" title="Duplicate" onClick={() => setBlocks([...blocks.slice(0,index+1), structuredClone(block), ...blocks.slice(index+1)])} className="p-2 text-slate-500"><Copy className="h-4 w-4"/></button><button type="button" title={block.is_hidden?'Show':'Hide'} onClick={() => setBlocks(blocks.map((item,i)=>i===index?{...item,is_hidden:!item.is_hidden}:item))} className="p-2 text-slate-500">{block.is_hidden?<Eye className="h-4 w-4"/>:<EyeOff className="h-4 w-4"/>}</button><button type="button" title="Delete" onClick={() => setBlocks(blocks.filter((_,i)=>i!==index))} className="p-2 text-rose-600"><Trash2 className="h-4 w-4"/></button></div></header><div className="p-4"><BlockFields block={block} onChange={(next)=>setBlocks(blocks.map((item,i)=>i===index?next:item))}/></div></div>)}{!blocks.length&&<div className="rounded-lg border border-dashed p-12 text-center text-sm text-slate-500">Add the first block from the library.</div>}<div className="flex justify-end"><Button type="button" variant="brand" loading={saving} onClick={save}>Save blocks</Button></div></section>
    </div>;
}

export default function PageEditor({ page, blockDefinitions = [], previewUrl, revisions = [] }) {
    const title = page ? 'Edit: ' + page.title : 'Create page';
    return <AuthenticatedLayout header={<PageHeader title={title} subtitle="Structured blocks, publishing workflow, revisions, and page-level SEO." actions={<div className="flex gap-2">{previewUrl&&<Button href={previewUrl} variant="secondary" icon={ExternalLink}>Preview draft</Button>}<Button href={route('superadmin.website.index')} variant="secondary">Back</Button></div>}/>}><Head title={title} />{page?.status !== 'published'&&<Alert tone="info" className="mb-6">This page is not publicly visible. Use the signed preview while editing.</Alert>}<PageDetails page={page}/>{page&&<BlocksEditor page={page} blockDefinitions={blockDefinitions}/>} {page&&revisions.length>0&&<SectionCard title="Revision history" className="mt-6"><div className="divide-y">{revisions.map((revision)=><div key={revision.id} className="flex items-center justify-between py-3 text-sm"><span>Version {revision.version} · {new Date(revision.created_at).toLocaleString()}</span><button onClick={()=>router.post(route('superadmin.website.pages.revisions.restore',[page.id,revision.id]))} className="font-semibold text-indigo-600">Restore</button></div>)}</div></SectionCard>}</AuthenticatedLayout>;
}
