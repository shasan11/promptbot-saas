function format(value, options) {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', options).format(date);
}

export default function DateTimeDisplay({ value, withTime = false, relative, className = '' }) {
    if (!value) {
        return <span className={`text-slate-400 ${className}`}>—</span>;
    }

    const display = format(value, withTime
        ? { dateStyle: 'medium', timeStyle: 'short' }
        : { dateStyle: 'medium' });

    return (
        <time dateTime={value} title={format(value, { dateStyle: 'full', timeStyle: 'long' })} className={className}>
            {relative || display}
        </time>
    );
}
