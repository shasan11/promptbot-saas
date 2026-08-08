import {
    Cloud, Code2, Database, FileText, Globe, HelpCircle, Map, Plug, PenLine,
} from 'lucide-react';

const TYPES = {
    file: { label: 'Files', icon: FileText },
    website: { label: 'Web page', icon: Globe },
    website_crawl: { label: 'Website crawl', icon: Globe },
    sitemap: { label: 'Sitemap', icon: Map },
    faq: { label: 'FAQ', icon: HelpCircle },
    manual_text: { label: 'Manual text', icon: PenLine },
    integration: { label: 'Integration', icon: Plug },
    api: { label: 'API', icon: Code2 },
    database: { label: 'Database', icon: Database },
    external_storage: { label: 'External storage', icon: Cloud },
};

export default function SourceTypeBadge({ type, className = '' }) {
    const key = String(type?.value ?? type ?? '').toLowerCase();
    const config = TYPES[key] || { label: key.replaceAll('_', ' ') || 'Unknown', icon: FileText };
    const Icon = config.icon;

    return (
        <span className={`inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-600 ${className}`}>
            <Icon className="h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />
            {config.label}
        </span>
    );
}
