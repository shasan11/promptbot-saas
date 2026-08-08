import { X } from 'lucide-react';

export function FilterBar({ children, className = '' }) {
    return <div className={`flex flex-wrap items-center gap-3 ${className}`}>{children}</div>;
}

export function FilterChip({ label, onRemove }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-navy-800 py-1 pl-3 pr-1 text-xs font-medium text-white">
            {label}
            <button
                type="button"
                onClick={onRemove}
                aria-label={`Remove ${label} filter`}
                className="rounded-full p-0.5 hover:bg-white/20"
            >
                <X className="h-3 w-3" />
            </button>
        </span>
    );
}

export default FilterBar;
