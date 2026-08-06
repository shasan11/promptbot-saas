import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950';

function Field({ label, error, hint, children, className = '' }) {
    return (
        <label className={`block ${className}`}>
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            <div className="mt-2">{children}</div>
            {hint && !error && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
            {error && <p className="mt-1 text-xs font-semibold text-rose-600">{error}</p>}
        </label>
    );
}

function Panel({ title, subtitle, children }) {
    return (
        <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-6">
                <h2 className="text-base font-bold text-slate-950">{title}</h2>
                {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
            </div>
            <div className="grid gap-5 md:grid-cols-2">{children}</div>
        </section>
    );
}

const totalItems = (items) => items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_amount) || 0), 0);
const taxFor = (items, rate) => Math.round(totalItems(items) * (Number(rate) || 0)) / 100;
const money = (value) => (Number.isFinite(Number(value)) ? Number(value).toFixed(2) : '0.00');

export default function Create({ tenants = [], defaults = {} }) {
    const initialItems = [{ description: '', quantity: 1, unit_amount: 0 }];
    const { data, setData, post, processing, errors } = useForm({
        tenant_id: tenants[0]?.id || '',
        status: 'open',
        currency: defaults.currency || 'USD',
        issued_on: new Date().toISOString().slice(0, 10),
        due_on: '',
        tax_rate: defaults.taxRate ?? 0,
        tax_total: taxFor(initialItems, defaults.taxRate ?? 0),
        items: initialItems,
    });

    const replaceItems = (items) => setData({ ...data, items, tax_total: taxFor(items, data.tax_rate) });

    const updateItem = (index, key, value) => {
        replaceItems(data.items.map((item, itemIndex) => (itemIndex === index ? { ...item, [key]: value } : item)));
    };

    const addItem = () => replaceItems([...data.items, { description: '', quantity: 1, unit_amount: 0 }]);
    const removeItem = (index) => replaceItems(data.items.filter((_, itemIndex) => itemIndex !== index));
    const subtotal = totalItems(data.items);
    const total = subtotal + (Number(data.tax_total) || 0);

    const updateTaxRate = (rate) => setData({ ...data, tax_rate: rate, tax_total: taxFor(data.items, rate) });

    const submit = (event) => {
        event.preventDefault();
        post(route('superadmin.billing.invoices.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Create Invoice"
                    subtitle={`Issue a tenant invoice using the configured ${defaults.prefix || 'INV'} numbering sequence.`}
                    actions={<Link href={route('superadmin.billing.invoices.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to invoices</Link>}
                />
            }
        >
            <Head title="Create Invoice" />

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6">
                <Panel title="Invoice details">
                    <Field label="Tenant" error={errors.tenant_id}>
                        <select className={inputClass} value={data.tenant_id} onChange={(event) => setData('tenant_id', event.target.value)}>
                            <option value="">Select tenant</option>
                            {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                        </select>
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            <option value="draft">Draft</option>
                            <option value="open">Open (ready to send)</option>
                        </select>
                    </Field>
                    <Field label="Currency" error={errors.currency}>
                        <input className={inputClass} value={data.currency} onChange={(event) => setData('currency', event.target.value.toUpperCase())} maxLength={3} />
                    </Field>
                    <Field label="Issued on" error={errors.issued_on}>
                        <input type="date" className={inputClass} value={data.issued_on} onChange={(event) => setData('issued_on', event.target.value)} />
                    </Field>
                    <Field label="Due on" error={errors.due_on}>
                        <input type="date" className={inputClass} value={data.due_on} onChange={(event) => setData('due_on', event.target.value)} />
                    </Field>
                    <Field label="Tax rate (%)" error={errors.tax_rate} hint={`Calculated tax: ${data.currency} ${money(data.tax_total)}`}>
                        <input type="number" min="0" max="100" step="0.01" className={inputClass} value={data.tax_rate} onChange={(event) => updateTaxRate(event.target.value)} />
                    </Field>
                </Panel>

                <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-5 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-bold text-slate-950">Line items</h2>
                            <p className="mt-1 text-sm text-slate-500">Quantity, unit totals, configured tax, and grand total are calculated automatically.</p>
                        </div>
                        <button type="button" onClick={addItem} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Add line</button>
                    </div>

                    <div className="space-y-3">
                        {data.items.map((item, index) => (
                            <div key={index} className="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_100px_140px_auto] md:items-start">
                                <Field label="Description" error={errors[`items.${index}.description`]}>
                                    <input className={inputClass} value={item.description} onChange={(event) => updateItem(index, 'description', event.target.value)} />
                                </Field>
                                <Field label="Qty" error={errors[`items.${index}.quantity`]}>
                                    <input type="number" min="1" className={inputClass} value={item.quantity} onChange={(event) => updateItem(index, 'quantity', event.target.value)} />
                                </Field>
                                <Field label="Unit amount" error={errors[`items.${index}.unit_amount`]}>
                                    <input type="number" min="0" step="0.01" className={inputClass} value={item.unit_amount} onChange={(event) => updateItem(index, 'unit_amount', event.target.value)} />
                                </Field>
                                <div className="flex items-end pb-0.5">
                                    <button type="button" disabled={data.items.length === 1} onClick={() => removeItem(index)} className="rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-rose-600 shadow-sm hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40">Remove</button>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-6 ml-auto max-w-xs space-y-2 text-sm">
                        <div className="flex justify-between text-slate-600"><span>Subtotal</span><span className="font-semibold text-slate-950">{data.currency} {money(subtotal)}</span></div>
                        <div className="flex justify-between text-slate-600"><span>Tax ({data.tax_rate || 0}%)</span><span className="font-semibold text-slate-950">{data.currency} {money(data.tax_total)}</span></div>
                        <div className="flex justify-between border-t border-slate-200 pt-2 text-base"><span className="font-bold text-slate-950">Total</span><span className="font-bold text-slate-950">{data.currency} {money(total)}</span></div>
                    </div>
                </section>

                <div className="flex justify-end gap-3">
                    <Link href={route('superadmin.billing.invoices.index')} className="rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Cancel</Link>
                    <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {processing ? 'Saving...' : 'Create invoice'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
