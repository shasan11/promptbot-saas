export default function CurrencyDisplay({ amount, currency = 'USD', className = '' }) {
    if (amount === null || amount === undefined) {
        return <span className={`text-slate-400 ${className}`}>—</span>;
    }

    const formatted = new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(Number(amount));

    return <span className={`font-mono tabular-nums ${className}`}>{formatted}</span>;
}
