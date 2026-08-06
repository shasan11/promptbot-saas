import PageHeader from '@/Components/Superadmin/PageHeader';
import StatusBadge from '@/Components/Superadmin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

function Detail({ label, value, children }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</div>
            <div className="mt-2 text-sm font-semibold text-slate-950">{children || value || '-'}</div>
        </div>
    );
}

export default function Show({ invoice }) {
    const items = invoice.items || [];
    const canManage = invoice.status !== 'paid' && invoice.status !== 'void';

    const markPaid = () => {
        if (window.confirm(`Mark invoice ${invoice.number} as paid?`)) {
            router.post(route('superadmin.billing.invoices.mark-paid', invoice.id), {}, { preserveScroll: true });
        }
    };

    const voidInvoice = () => {
        if (window.confirm(`Void invoice ${invoice.number}? This cannot be undone.`)) {
            router.post(route('superadmin.billing.invoices.void', invoice.id), {}, { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Invoice ${invoice.number}`}
                    subtitle={invoice.tenant?.company_name || 'Unknown tenant'}
                    actions={
                        <div className="flex gap-2">
                            {canManage && (
                                <>
                                    <button type="button" onClick={markPaid} className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">Mark as paid</button>
                                    <button type="button" onClick={voidInvoice} className="rounded-md border border-rose-300 bg-white px-4 py-2 text-sm font-bold text-rose-700 shadow-sm hover:bg-rose-50">Void</button>
                                </>
                            )}
                            <Link href={route('superadmin.billing.invoices.index')} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back</Link>
                        </div>
                    }
                />
            }
        >
            <Head title={`Invoice ${invoice.number}`} />

            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <Detail label="Status"><StatusBadge status={invoice.status} /></Detail>
                    <Detail label="Issued" value={invoice.issued_on} />
                    <Detail label="Due" value={invoice.due_on} />
                    <Detail label="Paid at" value={invoice.paid_at} />
                    <Detail label="Voided at" value={invoice.voided_at} />
                </div>

                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <h2 className="text-base font-bold text-slate-950">Line items</h2>
                    </div>
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Description</th>
                                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Qty</th>
                                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Unit amount</th>
                                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-6 py-4 text-slate-700">{item.description}</td>
                                    <td className="px-6 py-4 text-right text-slate-700">{item.quantity}</td>
                                    <td className="px-6 py-4 text-right text-slate-700">{invoice.currency} {item.unit_amount}</td>
                                    <td className="px-6 py-4 text-right font-semibold text-slate-950">{invoice.currency} {item.total}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="ml-auto max-w-xs space-y-2 px-6 py-5 text-sm">
                        <div className="flex justify-between text-slate-600"><span>Subtotal</span><span className="font-semibold text-slate-950">{invoice.currency} {invoice.subtotal}</span></div>
                        <div className="flex justify-between text-slate-600"><span>Tax</span><span className="font-semibold text-slate-950">{invoice.currency} {invoice.tax_total}</span></div>
                        <div className="flex justify-between border-t border-slate-200 pt-2 text-base"><span className="font-bold text-slate-950">Total</span><span className="font-bold text-slate-950">{invoice.currency} {invoice.total}</span></div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
