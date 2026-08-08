import { useId, useState } from 'react';

export default function Tooltip({ label, children, side = 'top' }) {
    const [open, setOpen] = useState(false);
    const id = useId();

    const positions = {
        top: 'bottom-full left-1/2 mb-2 -translate-x-1/2',
        bottom: 'top-full left-1/2 mt-2 -translate-x-1/2',
    };

    return (
        <span
            className="relative inline-flex"
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={() => setOpen(false)}
            onFocus={() => setOpen(true)}
            onBlur={() => setOpen(false)}
        >
            {typeof children === 'function' ? children({ 'aria-describedby': id }) : children}
            {open && (
                <span
                    id={id}
                    role="tooltip"
                    className={`pointer-events-none absolute z-50 whitespace-nowrap rounded-md bg-navy-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-soft-lg ${positions[side] || positions.top}`}
                >
                    {label}
                </span>
            )}
        </span>
    );
}
