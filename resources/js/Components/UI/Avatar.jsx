const palette = ['bg-navy-700', 'bg-brand-600', 'bg-blue-600', 'bg-amber-600', 'bg-rose-600', 'bg-indigo-600'];

function initials(name = '') {
    return name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || '?';
}

function colorFor(name = '') {
    const sum = [...name].reduce((total, char) => total + char.charCodeAt(0), 0);
    return palette[sum % palette.length];
}

const sizes = {
    sm: 'h-7 w-7 text-xs',
    md: 'h-9 w-9 text-sm',
    lg: 'h-12 w-12 text-base',
};

export default function Avatar({ name, src, size = 'md', className = '' }) {
    if (src) {
        return <img src={src} alt={name || ''} className={`rounded-full object-cover ${sizes[size] || sizes.md} ${className}`} />;
    }

    return (
        <span
            className={`flex shrink-0 items-center justify-center rounded-full font-semibold text-white ${colorFor(name)} ${sizes[size] || sizes.md} ${className}`}
            aria-hidden="true"
        >
            {initials(name)}
        </span>
    );
}
