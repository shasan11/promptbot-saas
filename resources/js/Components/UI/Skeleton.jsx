export default function Skeleton({ className = '' }) {
    return <div className={`animate-pulse rounded bg-slate-200 ${className}`} aria-hidden="true" />;
}

export function SkeletonTable({ rows = 5, columns = 4 }) {
    return (
        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
            <div className="divide-y divide-slate-100">
                {Array.from({ length: rows }).map((_, rowIndex) => (
                    <div key={rowIndex} className="flex items-center gap-4 px-4 py-4">
                        {Array.from({ length: columns }).map((__, colIndex) => (
                            <Skeleton key={colIndex} className="h-4 flex-1" />
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}
