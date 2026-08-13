export default function Money({ value = 0, currency = 'USD' }) { return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value || 0)); }
