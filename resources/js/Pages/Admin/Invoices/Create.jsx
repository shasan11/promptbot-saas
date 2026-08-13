import PageHeader from '@/Components/Superadmin/PageHeader';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

const totalItems = (items) => items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_amount) || 0), 0);
const taxFor = (items, rate) => Math.round(totalItems(items) * (Number(rate) || 0)) / 100;
const money = (value) => (Number.isFinite(Number(value)) ? Number(value).toFixed(2) : '0.00');

export default function Create({ tenants = [], accounts = [], defaults = {}, selectedTenantId = null, selectedAccountId = null, billingModeSupport = 'both' }) {
    const selectedTenant = tenants.find((tenant) => tenant.id === selectedTenantId);
    const initialAccountId = selectedAccountId || selectedTenant?.customer_account_id || accounts[0]?.id || '';
    const initialItems = [{ tenant_id: selectedTenantId || '', description: '', quantity: 1, unit_amount: 0 }];
    const { data, setData, post, processing, errors } = useForm({
        customer_account_id: initialAccountId,
        tenant_id: selectedTenantId || (billingModeSupport === 'per_service' ? tenants.find((tenant) => String(tenant.customer_account_id) === String(initialAccountId))?.id || '' : ''),
        status: 'open',
        currency: defaults.currency || 'USD',
        issued_on: new Date().toISOString().slice(0, 10),
        due_on: '',
        tax_rate: defaults.taxRate ?? 0,
        tax_total: taxFor(initialItems, defaults.taxRate ?? 0),
        items: initialItems,
    });

    const replaceItems = (items) => setData({ ...data, items, tax_total: taxFor(items, data.tax_rate) });
    const updateItem = (index, key, value) => replaceItems(data.items.map((item, itemIndex) => (itemIndex === index ? { ...item, [key]: value } : item)));
    const accountTenants = tenants.filter((tenant) => String(tenant.customer_account_id) === String(data.customer_account_id));
    const addItem = () => replaceItems([...data.items, { tenant_id: data.tenant_id || '', description: '', quantity: 1, unit_amount: 0 }]);
    const removeItem = (index) => replaceItems(data.items.filter((_, itemIndex) => itemIndex !== index));
    const subtotal = totalItems(data.items);
    const total = subtotal + (Number(data.tax_total) || 0);
    const updateTaxRate = (rate) => setData({ ...data, tax_rate: rate, tax_total: taxFor(data.items, rate) });
    const changeAccount = (accountId) => {
        const tenantId = billingModeSupport === 'per_service'
            ? tenants.find((tenant) => String(tenant.customer_account_id) === String(accountId))?.id || ''
            : '';
        setData({ ...data, customer_account_id: accountId, tenant_id: tenantId, items: data.items.map((item) => ({ ...item, tenant_id: tenantId })) });
    };
    const changeTenant = (tenantId) => setData({ ...data, tenant_id: tenantId, items: data.items.map((item) => ({ ...item, tenant_id: tenantId })) });
    const errorCount = Object.keys(errors).length;

    const submit = (event) => {
        event.preventDefault();
        post(route('superadmin.billing.invoices.store'));
    };

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title="Create invoice"
                    subtitle={`Issue a service-specific or consolidated account invoice using the configured ${defaults.prefix || 'INV'} sequence.`}
                    actions={<Button href={route('superadmin.billing.invoices.index')} variant="secondary">Back to invoices</Button>}
                />
            )}
        >
            <Head title="Create invoice" />

            {errorCount > 0 && (
                <Alert tone="danger" title={`${errorCount} field${errorCount === 1 ? '' : 's'} need attention`} className="mb-6" />
            )}

            <form onSubmit={submit} className="grid gap-6 xl:grid-cols-[1fr_300px]">
                <div className="space-y-6">
                    <SectionCard title="Invoice details">
                        <div className="grid gap-5 md:grid-cols-2">
                            <FormField id="customer_account_id" label="Customer account" required error={errors.customer_account_id}>
                                <Select id="customer_account_id" value={data.customer_account_id} error={!!errors.customer_account_id} onChange={(event) => changeAccount(event.target.value)}>
                                    <option value="">Select account</option>
                                    {accounts.map((account) => <option key={account.id} value={account.id}>{account.name} · {account.account_number}</option>)}
                                </Select>
                            </FormField>
                            <FormField id="tenant_id" label="Invoice scope" optional error={errors.tenant_id} hint="Leave blank for one consolidated account invoice.">
                                <Select id="tenant_id" value={data.tenant_id} error={!!errors.tenant_id} onChange={(event) => changeTenant(event.target.value)}>
                                    {billingModeSupport === 'both' && <option value="">Consolidated account invoice</option>}
                                    {accountTenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                                </Select>
                            </FormField>
                            <FormField id="status" label="Status" required error={errors.status} hint={data.status === 'draft' ? 'Draft invoices are not visible to the tenant yet.' : 'Open invoices are ready to be sent and paid.'}>
                                <Select id="status" value={data.status} onChange={(event) => setData('status', event.target.value)}>
                                    <option value="draft">Draft</option>
                                    <option value="open">Open (ready to send)</option>
                                </Select>
                            </FormField>
                            <FormField id="currency" label="Currency" required error={errors.currency}>
                                <Input id="currency" value={data.currency} maxLength={3} error={!!errors.currency} onChange={(event) => setData('currency', event.target.value.toUpperCase())} />
                            </FormField>
                            <FormField id="issued_on" label="Issued on" required error={errors.issued_on}>
                                <Input id="issued_on" type="date" value={data.issued_on} error={!!errors.issued_on} onChange={(event) => setData('issued_on', event.target.value)} />
                            </FormField>
                            <FormField id="due_on" label="Due on" optional error={errors.due_on}>
                                <Input id="due_on" type="date" value={data.due_on} error={!!errors.due_on} onChange={(event) => setData('due_on', event.target.value)} />
                            </FormField>
                            <FormField id="tax_rate" label="Tax rate (%)" error={errors.tax_rate} hint={`Calculated tax: ${data.currency} ${money(data.tax_total)}`}>
                                <Input id="tax_rate" type="number" min="0" max="100" step="0.01" value={data.tax_rate} error={!!errors.tax_rate} onChange={(event) => updateTaxRate(event.target.value)} />
                            </FormField>
                        </div>
                    </SectionCard>

                    <SectionCard
                        title="Line items"
                        description="Quantity, unit totals, configured tax, and grand total are calculated automatically."
                        actions={<Button type="button" variant="secondary" size="sm" icon={Plus} onClick={addItem}>Add line</Button>}
                    >
                        <div className="space-y-3">
                            {data.items.map((item, index) => (
                                <div key={index} className="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-4 md:grid-cols-[180px_1fr_90px_140px_auto] md:items-start">
                                    <FormField label="Workspace" error={errors[`items.${index}.tenant_id`]}>
                                        <Select value={item.tenant_id || ''} error={!!errors[`items.${index}.tenant_id`]} onChange={(event) => updateItem(index, 'tenant_id', event.target.value)} disabled={!!data.tenant_id}>
                                            <option value="">Account-level line</option>
                                            {accountTenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.company_name}</option>)}
                                        </Select>
                                    </FormField>
                                    <FormField label="Description" error={errors[`items.${index}.description`]}>
                                        <Input value={item.description} error={!!errors[`items.${index}.description`]} onChange={(event) => updateItem(index, 'description', event.target.value)} />
                                    </FormField>
                                    <FormField label="Qty" error={errors[`items.${index}.quantity`]}>
                                        <Input type="number" min="1" value={item.quantity} error={!!errors[`items.${index}.quantity`]} onChange={(event) => updateItem(index, 'quantity', event.target.value)} />
                                    </FormField>
                                    <FormField label="Unit amount" error={errors[`items.${index}.unit_amount`]}>
                                        <Input type="number" min="0" step="0.01" value={item.unit_amount} error={!!errors[`items.${index}.unit_amount`]} onChange={(event) => updateItem(index, 'unit_amount', event.target.value)} />
                                    </FormField>
                                    <div className="flex items-end pb-0.5">
                                        <Button type="button" variant="ghost" size="sm" icon={Trash2} disabled={data.items.length === 1} onClick={() => removeItem(index)} aria-label="Remove line item" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </SectionCard>
                </div>

                <aside className="xl:sticky xl:top-20 xl:h-fit">
                    <SectionCard title="Summary">
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between text-slate-600"><dt>Subtotal</dt><dd className="font-mono font-semibold text-slate-900">{data.currency} {money(subtotal)}</dd></div>
                            <div className="flex justify-between text-slate-600"><dt>Tax ({data.tax_rate || 0}%)</dt><dd className="font-mono font-semibold text-slate-900">{data.currency} {money(data.tax_total)}</dd></div>
                            <div className="flex justify-between border-t border-slate-200 pt-3 text-base"><dt className="font-bold text-slate-900">Total</dt><dd className="font-mono font-bold text-slate-900">{data.currency} {money(total)}</dd></div>
                        </dl>
                        <Button type="submit" variant="brand" loading={processing} className="mt-5 w-full">Create invoice</Button>
                        <Button href={route('superadmin.billing.invoices.index')} variant="ghost" className="mt-2 w-full">Cancel</Button>
                    </SectionCard>
                </aside>
            </form>
        </AuthenticatedLayout>
    );
}
