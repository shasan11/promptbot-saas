import { Search, X } from 'lucide-react';

export default function SearchInput({ value, onChange, onClear, placeholder = 'Search…', className = '' }) {
    return (
        <div className={`relative ${className}`}>
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
            <input
                type="search"
                value={value}
                onChange={(event) => onChange?.(event.target.value)}
                placeholder={placeholder}
                aria-label={placeholder}
                className="block h-10 w-full rounded-md border border-slate-300 pl-9 pr-9 text-sm shadow-soft placeholder:text-slate-400 focus:border-navy-800 focus:outline-none focus:ring-2 focus:ring-navy-800"
            />
            {value && (
                <button
                    type="button"
                    onClick={onClear}
                    aria-label="Clear search"
                    className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                >
                    <X className="h-3.5 w-3.5" />
                </button>
            )}
        </div>
    );
}
