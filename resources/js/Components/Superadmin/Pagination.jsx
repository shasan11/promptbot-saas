import { router } from '@inertiajs/react';

export default function Pagination({ links = [] }) {
    if (!links.length) {
        return null;
    }

    const clean = (label) => label.replace('&laquo;', '<').replace('&raquo;', '>');

    return (
        <div className="mt-4 flex flex-wrap gap-2">
            {links.map((link, index) => (
                <button
                    key={`${link.label}-${index}`}
                    type="button"
                    disabled={!link.url}
                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })}
                    className={`rounded-md border px-3 py-2 text-sm font-semibold shadow-sm ${link.active ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'} disabled:cursor-not-allowed disabled:opacity-40`}
                >
                    {clean(link.label)}
                </button>
            ))}
        </div>
    );
}
