import {
    AlertTriangle, Archive, Ban, CheckCircle2, CircleDashed, Clock, Loader2, XCircle,
} from 'lucide-react';

/**
 * Status is never communicated by colour alone — each state carries an icon and
 * a text label, so the badge remains readable to colour-blind users and in
 * greyscale print.
 */
const STATES = {
    ready: { label: 'Ready', icon: CheckCircle2, className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    active: { label: 'Active', icon: CheckCircle2, className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    published: { label: 'Published', icon: CheckCircle2, className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    indexed: { label: 'Indexed', icon: CheckCircle2, className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    unchanged: { label: 'Unchanged', icon: CheckCircle2, className: 'border-slate-200 bg-slate-100 text-slate-600' },

    processing: { label: 'Processing', icon: Loader2, className: 'border-blue-200 bg-blue-50 text-blue-700', spin: true },
    running: { label: 'Running', icon: Loader2, className: 'border-blue-200 bg-blue-50 text-blue-700', spin: true },
    extracting: { label: 'Extracting', icon: Loader2, className: 'border-blue-200 bg-blue-50 text-blue-700', spin: true },
    chunking: { label: 'Chunking', icon: Loader2, className: 'border-blue-200 bg-blue-50 text-blue-700', spin: true },
    embedding: { label: 'Embedding', icon: Loader2, className: 'border-blue-200 bg-blue-50 text-blue-700', spin: true },
    indexing: { label: 'Indexing', icon: Loader2, className: 'border-blue-200 bg-blue-50 text-blue-700', spin: true },
    validating: { label: 'Validating', icon: Loader2, className: 'border-blue-200 bg-blue-50 text-blue-700', spin: true },
    retrying: { label: 'Retrying', icon: Loader2, className: 'border-amber-200 bg-amber-50 text-amber-700', spin: true },

    queued: { label: 'Queued', icon: Clock, className: 'border-indigo-200 bg-indigo-50 text-indigo-700' },
    uploaded: { label: 'Uploaded', icon: Clock, className: 'border-indigo-200 bg-indigo-50 text-indigo-700' },
    pending: { label: 'Pending', icon: Clock, className: 'border-indigo-200 bg-indigo-50 text-indigo-700' },
    discovered: { label: 'Discovered', icon: Clock, className: 'border-indigo-200 bg-indigo-50 text-indigo-700' },
    fetched: { label: 'Fetched', icon: Clock, className: 'border-indigo-200 bg-indigo-50 text-indigo-700' },

    partially_ready: { label: 'Partially ready', icon: AlertTriangle, className: 'border-amber-200 bg-amber-50 text-amber-700' },
    attention_required: { label: 'Needs attention', icon: AlertTriangle, className: 'border-amber-200 bg-amber-50 text-amber-700' },
    warning: { label: 'Needs attention', icon: AlertTriangle, className: 'border-amber-200 bg-amber-50 text-amber-700' },
    outdated: { label: 'Outdated', icon: AlertTriangle, className: 'border-amber-200 bg-amber-50 text-amber-700' },
    missing: { label: 'Missing', icon: AlertTriangle, className: 'border-amber-200 bg-amber-50 text-amber-700' },

    failed: { label: 'Failed', icon: XCircle, className: 'border-rose-200 bg-rose-50 text-rose-700' },

    draft: { label: 'Draft', icon: CircleDashed, className: 'border-slate-200 bg-slate-100 text-slate-600' },
    in_review: { label: 'In review', icon: Clock, className: 'border-indigo-200 bg-indigo-50 text-indigo-700' },
    disabled: { label: 'Disabled', icon: Ban, className: 'border-slate-200 bg-slate-100 text-slate-600' },
    cancelled: { label: 'Cancelled', icon: Ban, className: 'border-slate-200 bg-slate-100 text-slate-600' },
    excluded: { label: 'Excluded', icon: Ban, className: 'border-slate-200 bg-slate-100 text-slate-600' },
    archived: { label: 'Archived', icon: Archive, className: 'border-slate-200 bg-slate-100 text-slate-600' },
    completed: { label: 'Completed', icon: CheckCircle2, className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
};

const FALLBACK = { icon: CircleDashed, className: 'border-slate-200 bg-slate-100 text-slate-600' };

export default function KnowledgeStatusBadge({ status, label, className = '' }) {
    const key = String(status?.value ?? status ?? '').toLowerCase();
    const state = STATES[key] || FALLBACK;
    const Icon = state.icon;
    const text = label || state.label || key.replaceAll('_', ' ') || 'Unknown';

    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize ${state.className} ${className}`}>
            <Icon className={`h-3.5 w-3.5 shrink-0 ${state.spin ? 'animate-spin motion-reduce:animate-none' : ''}`} aria-hidden="true" />
            {text}
        </span>
    );
}
