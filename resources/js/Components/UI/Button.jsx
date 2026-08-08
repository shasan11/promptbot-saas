import { Link } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';

const variants = {
    primary: 'bg-navy-800 text-white hover:bg-navy-900 focus-visible:ring-navy-800',
    brand: 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:ring-brand-600',
    secondary: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus-visible:ring-navy-800',
    ghost: 'text-slate-600 hover:bg-slate-100 focus-visible:ring-navy-800',
    danger: 'bg-rose-600 text-white hover:bg-rose-700 focus-visible:ring-rose-600',
};

const sizes = {
    sm: 'h-8 px-3 text-xs gap-1.5',
    md: 'h-10 px-4 text-sm gap-2',
    lg: 'h-11 px-5 text-sm gap-2',
};

export default function Button({
    as,
    href,
    variant = 'primary',
    size = 'md',
    icon: Icon,
    loading = false,
    disabled = false,
    type = 'button',
    className = '',
    children,
    ...props
}) {
    const classes = `inline-flex min-w-[40px] items-center justify-center rounded-md font-semibold shadow-soft transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${variants[variant] || variants.primary} ${sizes[size] || sizes.md} ${className}`;

    const content = (
        <>
            {loading ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : Icon ? <Icon className="h-4 w-4" aria-hidden="true" /> : null}
            {children}
        </>
    );

    if (href) {
        return (
            <Link href={href} className={classes} aria-disabled={disabled || loading} {...props}>
                {content}
            </Link>
        );
    }

    return (
        <button type={type} className={classes} disabled={disabled || loading} {...props}>
            {content}
        </button>
    );
}
