import KnowledgeShell from '@/Components/Knowledge/KnowledgeShell';
import KnowledgeStatusBadge from '@/Components/Knowledge/KnowledgeStatusBadge';
import SourceTypeBadge from '@/Components/Knowledge/SourceTypeBadge';
import Pagination from '@/Components/Superadmin/Pagination';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import { Link, useForm } from '@inertiajs/react';
import { Globe, Plus } from 'lucide-react';
import { useState } from 'react';

export default function WebsitesIndex({ sources, pages, bases, syncFrequencies, crawlLimits, can }) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        knowledge_base: bases[0]?.uuid || '',
        source_type: 'website_crawl',
        url: '',
        name: '',
        sync_frequency: 'weekly',
        crawl: {
            max_pages: crawlLimits.default_max_pages,
            max_depth: crawlLimits.default_max_depth,
            allowed_paths: [],
            excluded_paths: ['/login/*', '/admin/*', '/cart/*'],
            include_subdomains: false,
            follow_external: false,
            respect_robots: true,
            use_sitemap: false,
        },
    });

    const setCrawl = (key, value) => setData('crawl', { ...data.crawl, [key]: value });
    const isSinglePage = data.source_type === 'website';

    return (
        <KnowledgeShell
            title="Websites"
            description="Index content from your public website so agents can answer from it — and keep it in sync as pages change."
            actions={can?.create && bases.length > 0 && (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="inline-flex items-center gap-1.5 rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800"
                >
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    Add website
                </button>
            )}
        >
            <SectionCard title="Website sources" className="mb-6">
                {sources.length ? (
                    <ul className="divide-y divide-slate-100">
                        {sources.map((source) => (
                            <li key={source.uuid} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div className="min-w-0">
                                    <Link href={route('tenant.admin.knowledge.sources.show', source.uuid)} className="font-medium text-slate-900 hover:text-brand-700">
                                        {source.name}
                                    </Link>
                                    <p className="mt-0.5 truncate text-xs text-slate-500">
                                        {source.configuration?.url} · {source.page_count} pages · {source.chunk_count} chunks
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <SourceTypeBadge type={source.source_type} />
                                    <KnowledgeStatusBadge status={source.status} />
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <EmptyState
                        icon={Globe}
                        title="No websites indexed"
                        description="Add your help centre or documentation site and PromptBot will discover its pages, index them, and re-check them on a schedule."
                        action={can?.create && bases.length > 0 && (
                            <button type="button" onClick={() => setOpen(true)} className="rounded-md bg-navy-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">
                                Add website
                            </button>
                        )}
                    />
                )}
            </SectionCard>

            {pages.data.length > 0 && (
                <SectionCard title="Crawled pages" padded={false}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    {['Page', 'Words', 'Chunks', 'Status', 'Last crawled'].map((h) => (
                                        <th key={h} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {pages.data.map((page) => (
                                    <tr key={page.uuid} className="hover:bg-slate-50">
                                        <td className="max-w-md px-4 py-3">
                                            <p className="truncate font-medium text-slate-800">{page.page_title || page.url}</p>
                                            <p className="truncate text-xs text-slate-400">{page.url}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{page.word_count}</td>
                                        <td className="px-4 py-3 text-slate-600">{page.chunk_count}</td>
                                        <td className="px-4 py-3"><KnowledgeStatusBadge status={page.status} /></td>
                                        <td className="px-4 py-3 text-xs text-slate-500">
                                            {page.last_crawled_at ? new Date(page.last_crawled_at).toLocaleString() : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-4 pb-4"><Pagination links={pages.links} /></div>
                </SectionCard>
            )}

            <Modal show={open} onClose={() => setOpen(false)} maxWidth="2xl">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(route('tenant.admin.knowledge.websites.store'), { onSuccess: () => { setOpen(false); reset(); } });
                    }}
                    className="p-6"
                >
                    <h2 className="text-lg font-bold text-slate-900">Add a website</h2>
                    <p className="mt-1 text-sm text-slate-500">Only public pages can be indexed.</p>

                    <div className="mt-5 space-y-4">
                        <FormField label="Knowledge base" required id="w-base" error={errors.knowledge_base}>
                            <Select id="w-base" value={data.knowledge_base} onChange={(e) => setData('knowledge_base', e.target.value)}>
                                {bases.map((base) => <option key={base.uuid} value={base.uuid}>{base.name}</option>)}
                            </Select>
                        </FormField>

                        <FormField label="What to index" id="w-type">
                            <Select id="w-type" value={data.source_type} onChange={(e) => setData('source_type', e.target.value)}>
                                <option value="website">A single page</option>
                                <option value="website_crawl">A whole site — discover and index linked pages</option>
                                <option value="sitemap">Use the site's sitemap.xml</option>
                            </Select>
                        </FormField>

                        <FormField label="URL" required id="w-url" error={errors.url}>
                            <Input id="w-url" type="url" value={data.url} onChange={(e) => setData('url', e.target.value)} placeholder="https://help.example.com" />
                        </FormField>

                        <FormField label="Name" id="w-name" hint="Defaults to the site's hostname." error={errors.name}>
                            <Input id="w-name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        </FormField>

                        <FormField label="Keep it up to date" id="w-sync">
                            <Select id="w-sync" value={data.sync_frequency} onChange={(e) => setData('sync_frequency', e.target.value)}>
                                {syncFrequencies.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
                            </Select>
                        </FormField>

                        {!isSinglePage && (
                            <div className="space-y-4 rounded-md border border-slate-200 bg-slate-50/60 p-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField label="Maximum pages" id="w-pages" error={errors['crawl.max_pages']} hint={`Up to ${crawlLimits.max_pages.toLocaleString()}.`}>
                                        <Input id="w-pages" type="number" value={data.crawl.max_pages} onChange={(e) => setCrawl('max_pages', Number(e.target.value))} />
                                    </FormField>
                                    <FormField label="Maximum depth" id="w-depth" error={errors['crawl.max_depth']} hint="How many links deep from the starting page.">
                                        <Input id="w-depth" type="number" value={data.crawl.max_depth} onChange={(e) => setCrawl('max_depth', Number(e.target.value))} />
                                    </FormField>
                                </div>

                                <FormField label="Only index these paths" id="w-allow" hint="One per line, e.g. /docs/* — leave blank for everything.">
                                    <textarea
                                        id="w-allow"
                                        rows={2}
                                        value={data.crawl.allowed_paths.join('\n')}
                                        onChange={(e) => setCrawl('allowed_paths', e.target.value.split('\n').filter(Boolean))}
                                        className="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-soft focus:border-navy-800 focus:outline-none focus:ring-2 focus:ring-navy-800"
                                    />
                                </FormField>

                                <FormField label="Never index these paths" id="w-exclude" hint="One per line.">
                                    <textarea
                                        id="w-exclude"
                                        rows={2}
                                        value={data.crawl.excluded_paths.join('\n')}
                                        onChange={(e) => setCrawl('excluded_paths', e.target.value.split('\n').filter(Boolean))}
                                        className="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-soft focus:border-navy-800 focus:outline-none focus:ring-2 focus:ring-navy-800"
                                    />
                                </FormField>

                                <div className="space-y-2.5">
                                    <Switch checked={data.crawl.include_subdomains} onChange={(v) => setCrawl('include_subdomains', v)} label="Include subdomains" />
                                    <Switch checked={data.crawl.respect_robots} onChange={(v) => setCrawl('respect_robots', v)} label="Respect robots.txt" description="Recommended — ignoring it can get the crawler blocked." />
                                    <Switch checked={data.crawl.use_sitemap} onChange={(v) => setCrawl('use_sitemap', v)} label="Start from sitemap.xml" description="Faster and more complete when the site publishes one." />
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setOpen(false)} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" disabled={processing || !data.url} className="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 disabled:opacity-50">
                            {processing ? 'Starting…' : isSinglePage ? 'Index page' : 'Start crawl'}
                        </button>
                    </div>
                </form>
            </Modal>
        </KnowledgeShell>
    );
}
