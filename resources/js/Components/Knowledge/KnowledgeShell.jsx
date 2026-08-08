import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle, BarChart3, Database, FileText, FlaskConical, Folder,
    Globe, HelpCircle, LayoutGrid, Library, ListChecks, PenLine, Settings,
} from 'lucide-react';

/**
 * Navigation shell for the Knowledge Base module.
 *
 * Items are filtered by the viewer's permissions rather than rendered disabled:
 * a link to a page that will 403 is worse than no link, and the sidebar doubles
 * as a map of what this user can actually do.
 */
const SECTIONS = [
    {
        label: 'Knowledge',
        items: [
            { label: 'Overview', route: 'tenant.admin.knowledge.index', pattern: 'tenant.admin.knowledge.index', icon: LayoutGrid, permission: 'knowledge.view' },
            { label: 'Knowledge bases', route: 'tenant.admin.knowledge.bases.index', pattern: 'tenant.admin.knowledge.bases.*', icon: Library, permission: 'knowledge.view' },
            { label: 'Collections', route: 'tenant.admin.knowledge.collections.index', pattern: 'tenant.admin.knowledge.collections.*', icon: Folder, permission: 'knowledge.view' },
        ],
    },
    {
        label: 'Content',
        items: [
            { label: 'Documents', route: 'tenant.admin.knowledge.documents.index', pattern: 'tenant.admin.knowledge.documents.*', icon: FileText, permission: 'knowledge.sources.view' },
            { label: 'Websites', route: 'tenant.admin.knowledge.websites.index', pattern: 'tenant.admin.knowledge.websites.*', icon: Globe, permission: 'knowledge.sources.view' },
            { label: 'FAQs', route: 'tenant.admin.knowledge.faqs.index', pattern: 'tenant.admin.knowledge.faqs.*', icon: HelpCircle, permission: 'knowledge.sources.view' },
            { label: 'Text sources', route: 'tenant.admin.knowledge.text-sources.index', pattern: 'tenant.admin.knowledge.text-sources.*', icon: PenLine, permission: 'knowledge.sources.view' },
            { label: 'All sources', route: 'tenant.admin.knowledge.sources.index', pattern: 'tenant.admin.knowledge.sources.*', icon: Database, permission: 'knowledge.sources.view' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { label: 'Processing', route: 'tenant.admin.knowledge.processing.index', pattern: 'tenant.admin.knowledge.processing.*', icon: ListChecks, permission: 'knowledge.sources.view' },
            { label: 'Failed sources', route: 'tenant.admin.knowledge.failed.index', pattern: 'tenant.admin.knowledge.failed.*', icon: AlertTriangle, permission: 'knowledge.sources.view' },
            { label: 'Retrieval playground', route: 'tenant.admin.knowledge.playground.index', pattern: 'tenant.admin.knowledge.playground.*', icon: FlaskConical, permission: 'knowledge.retrieval.test' },
            { label: 'Analytics', route: 'tenant.admin.knowledge.analytics.index', pattern: 'tenant.admin.knowledge.analytics.*', icon: BarChart3, permission: 'knowledge.analytics.view' },
            { label: 'Settings', route: 'tenant.admin.knowledge.settings.edit', pattern: 'tenant.admin.knowledge.settings.*', icon: Settings, permission: 'knowledge.settings.manage' },
        ],
    },
];

export default function KnowledgeShell({ title, description, actions, children }) {
    const { auth } = usePage().props;
    const can = (permission) => !permission || auth?.permissions?.includes(permission);

    const sections = SECTIONS
        .map((section) => ({ ...section, items: section.items.filter((item) => can(item.permission)) }))
        .filter((section) => section.items.length);

    const flatItems = sections.flatMap((section) => section.items);
    const activeItem = flatItems.find((item) => route().current(item.pattern)) || flatItems[0];

    return (
        <AuthenticatedLayout title="Knowledge base">
            <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
                {/* Narrow screens get a select rather than a squeezed sidebar. */}
                <div className="lg:hidden">
                    <label htmlFor="knowledge-nav" className="sr-only">Knowledge navigation</label>
                    <Select
                        id="knowledge-nav"
                        value={activeItem ? route(activeItem.route) : ''}
                        onChange={(event) => event.target.value && router.visit(event.target.value)}
                        disabled={!flatItems.length}
                    >
                        {sections.map((section) => (
                            <optgroup key={section.label} label={section.label}>
                                {section.items.map((item) => (
                                    <option key={item.route} value={route(item.route)}>{item.label}</option>
                                ))}
                            </optgroup>
                        ))}
                    </Select>
                </div>

                <nav className="hidden space-y-5 lg:block" aria-label="Knowledge base navigation">
                    {sections.map((section) => (
                        <div key={section.label}>
                            <p className="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                                {section.label}
                            </p>
                            <div className="space-y-0.5">
                                {section.items.map((item) => {
                                    const Icon = item.icon;
                                    const isActive = route().current(item.pattern);

                                    return (
                                        <Link
                                            key={item.route}
                                            href={route(item.route)}
                                            aria-current={isActive ? 'page' : undefined}
                                            className={`flex h-9 items-center gap-2.5 rounded-md px-2.5 text-sm font-medium transition-colors ${
                                                isActive ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-100 hover:text-navy-900'
                                            }`}
                                        >
                                            <Icon className={`h-4 w-4 ${isActive ? 'text-brand-600' : 'text-slate-400'}`} strokeWidth={1.8} aria-hidden="true" />
                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </nav>

                <div className="min-w-0">
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h1 className="text-xl font-bold tracking-tight text-slate-900">{title}</h1>
                            {description && <p className="mt-1 max-w-2xl text-sm text-slate-500">{description}</p>}
                        </div>
                        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                    </div>
                    {children}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
