import { forwardRef } from 'react';

const Textarea = forwardRef(function Textarea({ error = false, className = '', rows = 4, ...props }, ref) {
    return (
        <textarea
            ref={ref}
            rows={rows}
            className={`block w-full rounded-md border shadow-soft transition placeholder:text-slate-400 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 sm:text-sm ${
                error ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-300 focus:border-navy-800 focus:ring-navy-800'
            } ${className}`}
            {...props}
        />
    );
});

export default Textarea;
