import { Link } from '@inertiajs/react';

const BUCKETS = [
    { key: 'healthy', label: 'Healthy', bar: 'bg-emerald-500', text: 'text-emerald-700', filter: 'ready' },
    { key: 'processing', label: 'Processing', bar: 'bg-blue-500', text: 'text-blue-700', filter: 'processing' },
    { key: 'needs_attention', label: 'Needs attention', bar: 'bg-amber-500', text: 'text-amber-700', filter: 'attention_required' },
    { key: 'failed', label: 'Failed', bar: 'bg-rose-500', text: 'text-rose-700', filter: 'failed' },
    { key: 'outdated', label: 'Outdated', bar: 'bg-slate-400', text: 'text-slate-600', filter: 'outdated' },
];

/**
 * Knowledge health at a glance. Each figure links through to the filtered
 * source list, because a count nobody can act on is decoration.
 */
export default function HealthSummary({ health = {} }) {
    const total = BUCKETS.reduce((sum, bucket) => sum + (health[bucket.key] || 0), 0);

    if (!total) {
        return (
            <p className="text-sm text-slate-500">
                No sources yet. Health appears here once you add knowledge.
            </p>
        );
    }

    return (
        <div>
            <div className="flex h-2.5 w-full overflow-hidden rounded-full bg-slate-100" role="img" aria-label={`Source health: ${BUCKETS.map((b) => `${health[b.key] || 0} ${b.label}`).join(', ')}`}>
                {BUCKETS.map((bucket) => {
                    const count = health[bucket.key] || 0;

                    if (!count) return null;

                    return (
                        <div
                            key={bucket.key}
                            className={bucket.bar}
                            style={{ width: `${(count / total) * 100}%` }}
                        />
                    );
                })}
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                {BUCKETS.map((bucket) => (
                    <div key={bucket.key}>
                        <dt className="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                            <span className={`h-2 w-2 rounded-full ${bucket.bar}`} aria-hidden="true" />
                            {bucket.label}
                        </dt>
                        <dd className="mt-0.5">
                            <Link
                                href={route('tenant.admin.knowledge.sources.index', { status: bucket.filter })}
                                className={`text-lg font-bold tracking-tight ${bucket.text} hover:underline`}
                            >
                                {(health[bucket.key] || 0).toLocaleString()}
                            </Link>
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
