import Avatar from '@/Components/UI/Avatar';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { BriefcaseBusiness, Camera, CheckCircle2, Clock3, KeyRound, Mail, MapPin, Phone, Save, ShieldCheck, Trash2, UsersRound } from 'lucide-react';
import { useState } from 'react';

function formatDate(value, fallback = 'Not available') {
    return value ? new Date(value).toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : fallback;
}

export default function Edit({ profile, locales = [], timezones = [] }) {
    const tenant = usePage().props.tenant;
    const [preview, setPreview] = useState(profile.avatarUrl);
    const details = useForm({
        name: profile.name || '',
        email: profile.email || '',
        phone: profile.phone || '',
        job_title: profile.jobTitle || '',
        locale: profile.locale || 'en',
        timezone: profile.timezone || 'UTC',
        avatar: null,
        remove_avatar: false,
    });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    const updateDetails = (event) => {
        event.preventDefault();
        details.post(route('tenant.admin.profile.update'), { forceFormData: true, preserveScroll: true });
    };

    const updatePassword = (event) => {
        event.preventDefault();
        password.put(route('tenant.admin.profile.password'), {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    };

    const chooseAvatar = (event) => {
        const file = event.target.files?.[0];
        if (!file) return;
        details.setData({ ...details.data, avatar: file, remove_avatar: false });
        setPreview(URL.createObjectURL(file));
    };

    const removeAvatar = () => {
        details.setData({ ...details.data, avatar: null, remove_avatar: true });
        setPreview(null);
    };

    return (
        <AuthenticatedLayout title="My profile">
            <Head title="My profile" />

            <section className="relative mb-5 overflow-hidden rounded-xl bg-navy-900 px-5 py-6 text-white shadow-soft-lg sm:px-6">
                <div className="pointer-events-none absolute inset-0 opacity-[0.12] [background-image:linear-gradient(rgba(255,255,255,.45)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.45)_1px,transparent_1px)] [background-size:32px_32px]" />
                <div className="pointer-events-none absolute -right-12 -top-20 h-56 w-56 rounded-full bg-brand-500/30 blur-3xl" />
                <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center">
                    <Avatar name={profile.name} src={preview} size="lg" className="!h-16 !w-16 ring-4 ring-white/10" />
                    <div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h1 className="truncate text-2xl font-bold tracking-tight">{profile.name}</h1><Badge tone="brand">{profile.status}</Badge></div><p className="mt-1 text-sm text-slate-300">{profile.jobTitle || 'Team member'} · {tenant?.companyName || 'Workspace'}</p><div className="mt-3 flex flex-wrap gap-2">{profile.roles.map((role) => <span key={role.id} className="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-slate-200 ring-1 ring-white/10">{role.label || role.name}</span>)}</div></div>
                    <div className="flex items-center gap-2 text-xs text-slate-300"><CheckCircle2 className="h-4 w-4 text-brand-400" />Account active</div>
                </div>
            </section>

            <div className="grid gap-5 xl:grid-cols-[280px_minmax(0,1fr)]">
                <aside className="space-y-5">
                    <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                        <div className="flex flex-col items-center text-center">
                            <Avatar name={profile.name} src={preview} size="lg" className="!h-24 !w-24 ring-4 ring-brand-50" />
                            <h2 className="mt-3 font-semibold text-slate-900">Profile photo</h2><p className="mt-1 text-xs leading-5 text-slate-500">PNG, JPG or WebP. Maximum size 2 MB.</p>
                            <div className="mt-4 flex flex-wrap justify-center gap-2"><label className="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-soft hover:bg-slate-50"><Camera className="h-3.5 w-3.5" />Choose photo<input type="file" accept="image/png,image/jpeg,image/webp" onChange={chooseAvatar} className="sr-only" /></label>{preview && <button type="button" onClick={removeAvatar} className="inline-flex h-8 items-center gap-1.5 rounded-md px-2 text-xs font-semibold text-rose-600 hover:bg-rose-50"><Trash2 className="h-3.5 w-3.5" />Remove</button>}</div>
                            {details.errors.avatar && <p className="mt-2 text-xs font-medium text-rose-600">{details.errors.avatar}</p>}
                        </div>
                    </section>

                    <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                        <h2 className="text-sm font-semibold text-slate-900">Workspace access</h2>
                        <dl className="mt-4 space-y-3 text-xs">
                            <div className="flex gap-2"><ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" /><div><dt className="text-slate-400">Roles</dt><dd className="mt-0.5 font-medium text-slate-700">{profile.roles.map((role) => role.label || role.name).join(', ') || 'No role assigned'}</dd></div></div>
                            <div className="flex gap-2"><UsersRound className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" /><div><dt className="text-slate-400">Teams</dt><dd className="mt-0.5 font-medium text-slate-700">{profile.teams.map((team) => team.name).join(', ') || 'No team assigned'}</dd></div></div>
                            <div className="flex gap-2"><BriefcaseBusiness className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" /><div><dt className="text-slate-400">Department</dt><dd className="mt-0.5 font-medium text-slate-700">{profile.department?.name || 'Not assigned'}</dd></div></div>
                        </dl>
                    </section>
                </aside>

                <main className="space-y-5">
                    <form onSubmit={updateDetails} className="rounded-lg border border-slate-200 bg-white shadow-soft">
                        <div className="border-b border-slate-100 px-5 py-4"><h2 className="text-sm font-semibold text-slate-900">Personal information</h2><p className="mt-0.5 text-xs text-slate-500">Keep your contact information and regional preferences current.</p></div>
                        <div className="grid gap-5 p-5 md:grid-cols-2">
                            <FormField label="Full name" required error={details.errors.name}><div className="relative"><UsersRound className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" /><Input value={details.data.name} onChange={(event) => details.setData('name', event.target.value)} className="pl-9" error={!!details.errors.name} /></div></FormField>
                            <FormField label="Email address" required error={details.errors.email}><div className="relative"><Mail className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" /><Input type="email" value={details.data.email} onChange={(event) => details.setData('email', event.target.value)} className="pl-9" error={!!details.errors.email} /></div></FormField>
                            <FormField label="Phone" optional error={details.errors.phone}><div className="relative"><Phone className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" /><Input value={details.data.phone} onChange={(event) => details.setData('phone', event.target.value)} className="pl-9" placeholder="+1 555 123 4567" /></div></FormField>
                            <FormField label="Job title" optional error={details.errors.job_title}><div className="relative"><BriefcaseBusiness className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" /><Input value={details.data.job_title} onChange={(event) => details.setData('job_title', event.target.value)} className="pl-9" placeholder="Customer Success Manager" /></div></FormField>
                            <FormField label="Language" required error={details.errors.locale}><Select value={details.data.locale} onChange={(event) => details.setData('locale', event.target.value)}>{locales.map((locale) => <option key={locale.value} value={locale.value}>{locale.label}</option>)}</Select></FormField>
                            <FormField label="Timezone" required error={details.errors.timezone}><Select value={details.data.timezone} onChange={(event) => details.setData('timezone', event.target.value)}>{timezones.map((timezone) => <option key={timezone.value} value={timezone.value}>{timezone.label}</option>)}</Select></FormField>
                        </div>
                        <div className="flex items-center justify-end border-t border-slate-100 bg-slate-50/40 px-5 py-3"><Button type="submit" variant="brand" size="sm" icon={Save} loading={details.processing}>Save profile</Button></div>
                    </form>

                    <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
                        <form onSubmit={updatePassword} className="rounded-lg border border-slate-200 bg-white shadow-soft">
                            <div className="border-b border-slate-100 px-5 py-4"><div className="flex items-center gap-2"><span className="flex h-8 w-8 items-center justify-center rounded-md bg-brand-50 text-brand-700"><KeyRound className="h-4 w-4" /></span><div><h2 className="text-sm font-semibold text-slate-900">Change password</h2><p className="mt-0.5 text-xs text-slate-500">Use a strong password you don’t use elsewhere.</p></div></div></div>
                            <div className="grid gap-4 p-5 sm:grid-cols-2"><FormField label="Current password" error={password.errors.current_password} className="sm:col-span-2"><Input type="password" value={password.data.current_password} onChange={(event) => password.setData('current_password', event.target.value)} autoComplete="current-password" /></FormField><FormField label="New password" error={password.errors.password}><Input type="password" value={password.data.password} onChange={(event) => password.setData('password', event.target.value)} autoComplete="new-password" /></FormField><FormField label="Confirm password" error={password.errors.password_confirmation}><Input type="password" value={password.data.password_confirmation} onChange={(event) => password.setData('password_confirmation', event.target.value)} autoComplete="new-password" /></FormField></div>
                            <div className="flex justify-end border-t border-slate-100 bg-slate-50/40 px-5 py-3"><Button type="submit" variant="secondary" size="sm" icon={KeyRound} loading={password.processing}>Update password</Button></div>
                        </form>

                        <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft"><h2 className="text-sm font-semibold text-slate-900">Account activity</h2><dl className="mt-4 space-y-4 text-xs"><div className="flex gap-2"><Clock3 className="mt-0.5 h-4 w-4 text-slate-400" /><div><dt className="text-slate-400">Last sign in</dt><dd className="mt-0.5 font-medium text-slate-700">{formatDate(profile.lastLoginAt, 'Not recorded')}</dd></div></div><div className="flex gap-2"><KeyRound className="mt-0.5 h-4 w-4 text-slate-400" /><div><dt className="text-slate-400">Password changed</dt><dd className="mt-0.5 font-medium text-slate-700">{formatDate(profile.passwordChangedAt, 'Not recorded')}</dd></div></div><div className="flex gap-2"><MapPin className="mt-0.5 h-4 w-4 text-slate-400" /><div><dt className="text-slate-400">Member since</dt><dd className="mt-0.5 font-medium text-slate-700">{formatDate(profile.createdAt)}</dd></div></div></dl></section>
                    </div>
                </main>
            </div>
        </AuthenticatedLayout>
    );
}
