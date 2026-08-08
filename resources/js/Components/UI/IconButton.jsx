const variants = {
    default: 'text-slate-500 hover:bg-slate-100 hover:text-slate-900',
    danger: 'text-rose-600 hover:bg-rose-50',
    inverse: 'text-slate-300 hover:bg-white/10 hover:text-white',
};

export default function IconButton({ icon: Icon, label, variant = 'default', className = '', ...props }) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            className={`inline-flex h-10 w-10 items-center justify-center rounded-md transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-800 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${variants[variant] || variants.default} ${className}`}
            {...props}
        >
            <Icon className="h-[18px] w-[18px]" aria-hidden="true" />
        </button>
    );
}
