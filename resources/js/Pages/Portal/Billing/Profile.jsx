import Panel from '@/Components/Portal/Panel';
import PortalLayout from '@/Layouts/PortalLayout';
import { useForm } from '@inertiajs/react';

export default function Profile({ billingProfile }) {
    const form = useForm({
        billing_name: billingProfile?.billing_name || '', billing_email: billingProfile?.billing_email || '', company_name: billingProfile?.company_name || '',
        address_line_1: billingProfile?.address_line_1 || '', address_line_2: billingProfile?.address_line_2 || '', city: billingProfile?.city || '',
        state: billingProfile?.state || '', country: billingProfile?.country || '', postal_code: billingProfile?.postal_code || '',
        tax_number: billingProfile?.tax_number || '', vat_number: billingProfile?.vat_number || '', currency: billingProfile?.currency || 'USD',
    });
    const fields = [['billing_name','Billing name'],['billing_email','Billing email','email'],['company_name','Company'],['address_line_1','Address line 1'],['address_line_2','Address line 2'],['city','City'],['state','State / region'],['country','Country code'],['postal_code','Postal code'],['tax_number','Tax number'],['vat_number','VAT number'],['currency','Currency']];
    return <PortalLayout title="Billing profile"><Panel title="Invoice identity" description="Invoices snapshot these values when issued, so updates do not rewrite historical documents."><form onSubmit={event => { event.preventDefault(); form.put(route('portal.billing.profile.update')); }} className="grid gap-5 sm:grid-cols-2">{fields.map(([key,label,type='text']) => <label key={key} className="text-sm font-medium">{label}<input type={type} required={['billing_name','billing_email','address_line_1','city','country','postal_code','currency'].includes(key)} maxLength={key === 'country' ? 2 : key === 'currency' ? 3 : undefined} value={form.data[key]} onChange={event => form.setData(key, ['country','currency'].includes(key) ? event.target.value.toUpperCase() : event.target.value)} className="mt-1.5 w-full rounded-lg border-slate-300" />{form.errors[key] && <span className="text-xs text-rose-600">{form.errors[key]}</span>}</label>)}<div className="sm:col-span-2"><button disabled={form.processing} className="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white">Save billing profile</button></div></form></Panel></PortalLayout>;
}
