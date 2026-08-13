import PageHeader from '@/Components/Superadmin/PageHeader';
import { SectionCard } from '@/Components/UI/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ defaults = {}, account = null }) {
    const editing = Boolean(account);
    const form = useForm({
        name: account?.name || '', legal_name: account?.legal_name || '', owner_name: '', owner_email: '',
        billing_email: account?.billing_email || '', currency: defaults.currency || 'USD',
        default_currency: account?.default_currency || defaults.currency || 'USD', timezone: account?.timezone || defaults.timezone || 'UTC',
        billing_mode: account?.billing_mode || defaults.billing_mode || 'per_service',
    });
    const fields = [
        ['name', 'Account name', true], ['legal_name', 'Legal name', false],
        ...(!editing ? [['owner_name', 'Owner name', true], ['owner_email', 'Owner email', true]] : []),
        ['billing_email', 'Billing email', false], [editing ? 'default_currency' : 'currency', 'Currency', true], ['timezone', 'Timezone', true],
    ];
    const submit = event => { event.preventDefault(); editing ? form.put(route('superadmin.customers.accounts.update', account.public_uuid || account.id)) : form.post(route('superadmin.customers.accounts.store')); };
    return <AuthenticatedLayout header={<PageHeader title={editing ? `Edit ${account.name}` : 'Create customer account'} subtitle={editing ? 'Update the account identity and commercial billing preferences.' : 'Create the commercial account and either link an existing portal user or invite a new owner.'} />}><Head title={editing ? 'Edit account' : 'Create customer account'} /><SectionCard><form onSubmit={submit} className="space-y-5"><div className="grid gap-4 sm:grid-cols-2">{fields.map(([key, label, required]) => <label key={key} className="text-sm font-medium">{label}<input required={required} value={form.data[key]} onChange={event => form.setData(key, event.target.value)} className="mt-1.5 w-full rounded-lg border-slate-300" />{form.errors[key] && <span className="text-xs text-rose-600">{form.errors[key]}</span>}</label>)}</div><label className="block text-sm font-medium">Billing mode<select value={form.data.billing_mode} onChange={event => form.setData('billing_mode', event.target.value)} className="mt-1.5 w-full rounded-lg border-slate-300"><option value="per_service">Per service</option><option value="consolidated">Consolidated</option></select></label><div className="flex justify-end"><button disabled={form.processing} className="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">{editing ? 'Save account' : 'Create account'}</button></div></form></SectionCard></AuthenticatedLayout>;
}
