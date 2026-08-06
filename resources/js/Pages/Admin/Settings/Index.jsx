import PageHeader from '@/Components/Superadmin/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ImageOff, Upload } from 'lucide-react';
import { useRef, useState } from 'react';

const inputClass = 'w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';

function ColorField({ value, disabled, onChange }) {
    const hex = /^#[0-9A-Fa-f]{6}$/.test(value ?? '') ? value : '#000000';

    return (
        <div className="mt-2 flex items-center gap-3">
            <input
                type="color"
                disabled={disabled}
                value={hex}
                onChange={(event) => onChange(event.target.value)}
                className="h-10 w-12 shrink-0 cursor-pointer rounded-md border border-slate-300 p-1 disabled:cursor-not-allowed"
                aria-label="Pick color"
            />
            <input
                type="text"
                disabled={disabled}
                value={value ?? ''}
                placeholder="#0F172A"
                maxLength={7}
                onChange={(event) => onChange(event.target.value)}
                className={`${inputClass} font-mono uppercase`}
            />
        </div>
    );
}

function ImageField({ field, file, removeChecked, disabled, onFile, onRemoveToggle }) {
    const inputRef = useRef(null);
    const previewUrl = file ? URL.createObjectURL(file) : (!removeChecked ? field.currentImageUrl : null);
    const hasCurrentImage = Boolean(field.currentImageUrl);

    return (
        <div className="mt-2">
            <div className="flex items-center gap-4">
                <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50">
                    {previewUrl ? (
                        <img src={previewUrl} alt="" className="h-full w-full object-contain" />
                    ) : (
                        <ImageOff className="h-5 w-5 text-slate-300" strokeWidth={1.5} />
                    )}
                </div>
                <div className="flex-1">
                    <input
                        ref={inputRef}
                        type="file"
                        accept={field.accept || 'image/*'}
                        disabled={disabled}
                        onChange={(event) => onFile(event.target.files?.[0] || null)}
                        className="hidden"
                    />
                    <button
                        type="button"
                        disabled={disabled}
                        onClick={() => inputRef.current?.click()}
                        className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Upload className="h-3.5 w-3.5" strokeWidth={1.8} />
                        {file ? file.name : 'Choose image'}
                    </button>
                    {hasCurrentImage && (
                        <label className="mt-2 flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input
                                type="checkbox"
                                disabled={disabled || Boolean(file)}
                                checked={removeChecked}
                                onChange={(event) => onRemoveToggle(event.target.checked)}
                                className="rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                            />
                            Remove current image
                        </label>
                    )}
                </div>
            </div>
        </div>
    );
}

function FieldControl({ field, value, disabled, onChange }) {
    if (field.type === 'select') {
        return (
            <select disabled={disabled} className={`${inputClass} mt-2`} value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
                {field.options.map((option) => <option key={`${field.key}-${option.value}`} value={option.value}>{option.label}</option>)}
            </select>
        );
    }

    if (field.type === 'textarea') {
        return <textarea disabled={disabled} className={`${inputClass} mt-2 min-h-28`} value={value ?? ''} placeholder={field.placeholder || ''} onChange={(event) => onChange(event.target.value)} />;
    }

    if (field.type === 'color') {
        return <ColorField value={value} disabled={disabled} onChange={onChange} />;
    }

    const htmlType = ['email', 'url', 'password', 'number'].includes(field.type) ? field.type : 'text';

    return (
        <input
            disabled={disabled}
            className={`${inputClass} mt-2`}
            type={htmlType}
            value={value ?? ''}
            placeholder={field.sensitive && field.configured ? 'Configured — leave blank to keep current value' : field.placeholder || ''}
            autoComplete={field.sensitive ? 'new-password' : undefined}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

function GroupForm({ group, canUpdate }) {
    const imageKeys = group.fields.filter((field) => field.type === 'image').map((field) => field.key);
    const initial = Object.fromEntries([
        ...group.fields.filter((field) => field.type !== 'image').map((field) => [field.key, field.value ?? '']),
        ...imageKeys.map((key) => [key, null]),
        ...imageKeys.map((key) => [`remove_${key}`, false]),
    ]);
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm(initial);

    const submit = (event) => {
        event.preventDefault();
        if (!canUpdate) return;
        // useForm.put() auto-switches to multipart POST + method spoofing
        // whenever a File is present in `data` (the image fields here).
        put(route('superadmin.system.settings.update', group.key), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold text-slate-950">{group.title}</h2>
                    <p className="mt-1 max-w-2xl text-sm text-slate-500">{group.description}</p>
                </div>
                {recentlySuccessful && <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Saved</span>}
                {!canUpdate && <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Read only</span>}
            </div>

            <div className="grid gap-5 md:grid-cols-2">
                {group.fields.map((field) => (
                    <label key={field.key} className={field.type === 'textarea' || field.type === 'image' ? 'block md:col-span-2' : 'block'}>
                        <span className="text-sm font-semibold text-slate-700">{field.label}</span>
                        {field.sensitive && field.configured && <span className="ml-2 text-xs font-medium text-emerald-600">Configured</span>}

                        {field.type === 'image' ? (
                            <ImageField
                                field={field}
                                file={data[field.key]}
                                removeChecked={data[`remove_${field.key}`]}
                                disabled={!canUpdate}
                                onFile={(file) => {
                                    setData(field.key, file);
                                    if (file) setData(`remove_${field.key}`, false);
                                }}
                                onRemoveToggle={(checked) => setData(`remove_${field.key}`, checked)}
                            />
                        ) : (
                            <FieldControl field={field} value={data[field.key]} disabled={!canUpdate} onChange={(value) => setData(field.key, value)} />
                        )}

                        {field.sensitive && <p className="mt-1 text-xs text-slate-500">Stored encrypted and never displayed after saving.</p>}
                        {field.help && <p className="mt-1 text-xs text-slate-500">{field.help}</p>}
                        {errors[field.key] && <p className="mt-1 text-xs font-semibold text-rose-600">{errors[field.key]}</p>}
                    </label>
                ))}
            </div>

            {canUpdate && (
                <div className="mt-6 flex justify-end">
                    <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {processing ? 'Saving...' : `Save ${group.title}`}
                    </button>
                </div>
            )}
        </form>
    );
}

function MailTest() {
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({ recipient: '' });

    const submit = (event) => {
        event.preventDefault();
        post(route('superadmin.system.settings.test-mail'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold text-slate-950">Test mail delivery</h2>
                    <p className="mt-1 text-sm text-slate-500">Save Email Identity and Mail Delivery first, then send a real test message.</p>
                </div>
                {recentlySuccessful && <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Sent</span>}
            </div>
            <div className="mt-5 flex flex-col gap-3 sm:flex-row">
                <div className="flex-1">
                    <input type="email" className={inputClass} placeholder="Recipient email address" value={data.recipient} onChange={(event) => setData('recipient', event.target.value)} />
                    {errors.recipient && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.recipient}</p>}
                </div>
                <button disabled={processing} className="rounded-md bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60">
                    {processing ? 'Sending...' : 'Send test email'}
                </button>
            </div>
        </form>
    );
}

export default function Index({ groups = [] }) {
    const { auth } = usePage().props;
    const canUpdate = auth?.permissions?.includes('settings.update');
    const [activeKey, setActiveKey] = useState(groups[0]?.key || 'general');
    const activeGroup = groups.find((group) => group.key === activeKey) || groups[0];

    return (
        <AuthenticatedLayout header={<PageHeader title="General Settings" subtitle="Configure platform identity, security, email, mail delivery, payments, AI/RAG, and branding." />}>
            <Head title="General Settings" />

            <div className="mx-auto max-w-6xl space-y-6">
                <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                    <div className="flex min-w-max gap-1">
                        {groups.map((group) => (
                            <button
                                key={group.key}
                                type="button"
                                onClick={() => setActiveKey(group.key)}
                                className={`rounded-md px-3 py-2 text-sm font-semibold transition ${activeKey === group.key ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'}`}
                            >
                                {group.title}
                            </button>
                        ))}
                    </div>
                </div>

                {activeGroup ? <GroupForm key={activeGroup.key} group={activeGroup} canUpdate={canUpdate} /> : (
                    <div className="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">No settings are configured.</div>
                )}

                {canUpdate && ['email', 'mail'].includes(activeKey) && <MailTest />}
            </div>
        </AuthenticatedLayout>
    );
}
