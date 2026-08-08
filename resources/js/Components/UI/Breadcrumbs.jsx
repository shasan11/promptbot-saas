import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

export default function Breadcrumbs({ items = [] }) {
    return (
        <nav aria-label="Breadcrumb">
            <ol className="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                {items.map((item, index) => (
                    <li key={item.label} className="flex items-center gap-1.5">
                        {index > 0 && <ChevronRight className="h-3 w-3 text-slate-300" aria-hidden="true" />}
                        {item.href && index !== items.length - 1 ? (
                            <Link href={item.href} className="font-medium hover:text-slate-800">{item.label}</Link>
                        ) : (
                            <span className={index === items.length - 1 ? 'font-medium text-slate-700' : ''} aria-current={index === items.length - 1 ? 'page' : undefined}>
                                {item.label}
                            </span>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
