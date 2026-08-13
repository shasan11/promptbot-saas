import PageHeader from '@/Components/Superadmin/PageHeader';
import { SectionCard } from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';

export default function Index({ query, results }) {
    const [value, setValue] = useState(query || '');
    return <AuthenticatedLayout header={<PageHeader title="Search" subtitle="Find customer accounts, portal users, services, invoices, subscriptions, and support tickets." />}><Head title="Search" /><SectionCard><form onSubmit={event => { event.preventDefault(); router.get(route('superadmin.search'), { q: value }); }} className="flex gap-3"><input autoFocus value={value} onChange={event => setValue(event.target.value)} className="min-w-0 flex-1 rounded-lg border-slate-300" placeholder="Name, email, account number, service slug, invoice or ticket…" /><button className="rounded-lg bg-slate-900 px-5 py-2 font-semibold text-white">Search</button></form>{query && (results.length ? <div className="mt-6 divide-y">{results.map((item, index) => <a key={`${item.type}-${index}`} href={item.url} className="grid gap-1 py-4 sm:grid-cols-[160px_1fr]"><span className="text-xs font-semibold uppercase tracking-wide text-indigo-600">{item.type}</span><span><span className="block font-semibold text-slate-900">{item.title}</span><span className="text-sm text-slate-500">{item.subtitle}</span></span></a>)}</div> : <EmptyState icon={Search} title="No results" description="Try a broader identifier or spelling." />)}</SectionCard></AuthenticatedLayout>;
}
