import PageHeader from '@/Components/Superadmin/PageHeader';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ImageOff, Upload } from 'lucide-react';
import { useRef, useState } from 'react';

const SENSITIVE_GROUPS = ['security', 'payment', 'ai_rag'];

function ColorField({ value, disabled, onChange }) {
    const hex = /^#[0-9A-Fa-f]{6}$/.test(value ?? '') ? value : '#000000';

    return (
        <div className="mt-2 flex items-center gap-3">
            <input type="color" disabled={disabled} value={hex} onChange={(event) => onChange(event.target.value)} className="h-10 w-12 shrink-0 cursor-pointer rounded-md border border-slate-300 p-1 disabled:cursor-not-allowed" aria-label="Pick color" />
            <Input type="text" disabled={disabled} value={value ?? ''} placeholder="#0F172A" maxLength={7} onChange={(event) => onChange(event.target.value)} className="font-mono uppercase" />
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
                    {previewUrl ? <img src={previewUrl} alt="" className="h-full w-full object-contain" /> : <ImageOff className="h-5 w-5 text-slate-300" strokeWidth={1.5} />}
                </div>
                <div className="flex-1">
                    <input ref={inputRef} type="file" accept={field.accept || 'image/*'} disabled={disabled} onChange={(event) => onFile(event.target.files?.[0] || null)} className="hidden" />
                    <Button type="button" variant="secondary" size="sm" icon={Upload} disabled={disabled} onClick={() => inputRef.current?.click()}>
                        {file ? file.name : 'Choose image'}
                    </Button>
                    {hasCurrentImage && (
                        <label className="mt-2 flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input type="checkbox" disabled={disabled || Boolean(file)} checked={removeChecked} onChange={(event) => onRemoveToggle(event.target.checked)} className="rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
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
            <Select disabled={disabled} value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
                {field.options.map((option) => <option key={`${field.key}-${option.value}`} value={option.value}>{option.label}</option>)}
            </Select>
        );
    }

    if (field.type === 'textarea') {
        return <Textarea disabled={disabled} value={value ?? ''} placeholder={field.placeholder || ''} onChange={(event) => onChange(event.target.value)} />;
    }

    if (field.type === 'color') {
        return <ColorField value={value} disabled={disabled} onChange={onChange} />;
    }

    const htmlType = ['email', 'url', 'password', 'number'].includes(field.type) ? field.type : 'text';

    return (
        <Input
            disabled={disabled}
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
    const { data, setData, put, processing, errors, recentlySuccessful, isDirty, reset } = useForm(initial);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const isSensitiveGroup = SENSITIVE_GROUPS.includes(group.key);

    const performSave = () => put(route('superadmin.system.settings.update', group.key), { preserveScroll: true });

    const submit = (event) => {
        event.preventDefault();
        if (!canUpdate) return;
        if (isSensitiveGroup) {
            setConfirmOpen(true);
            return;
        }
        performSave();
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-slate-200 bg-white p-6 shadow-soft">
            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold text-slate-900">{group.title}</h2>
                    <p className="mt-1 max-w-2xl text-sm text-slate-500">{group.description}</p>
                </div>
                {recentlySuccessful && <Badge tone="brand">Saved</Badge>}
                {!canUpdate && <Badge tone="neutral">Read only</Badge>}
            </div>

            <div className="grid gap-5 md:grid-cols-2">
                {group.fields.map((field) => (
                    <FormField
                        key={field.key}
                        label={<>{field.label}{field.sensitive && field.configured && <span className="ml-2 text-xs font-medium text-brand-700">Configured</span>}</>}
                        hint={field.sensitive ? 'Stored encrypted and never displayed after saving.' : field.help}
                        error={errors[field.key]}
                        className={field.type === 'textarea' || field.type === 'image' ? 'md:col-span-2' : ''}
                    >
                        {field.type === 'image' ? (
                            <ImageField
                                field={field}
                                file={data[field.key]}
                                removeChecked={data[`remove_${field.key}`]}
                                disabled={!canUpdate}
                                onFile={(file) => { setData(field.key, file); if (file) setData(`remove_${field.key}`, false); }}
                                onRemoveToggle={(checked) => setData(`remove_${field.key}`, checked)}
                            />
                        ) : (
                            <FieldControl field={field} value={data[field.key]} disabled={!canUpdate} onChange={(value) => setData(field.key, value)} />
                        )}
                    </FormField>
                ))}
            </div>

            {canUpdate && (
                <div className="mt-6 flex items-center justify-end gap-3">
                    {isDirty && <span className="mr-auto text-xs font-medium text-amber-700">Unsaved changes</span>}
                    <Button type="button" variant="secondary" disabled={!isDirty} onClick={() => reset()}>Reset</Button>
                    <Button type="submit" variant="brand" loading={processing} disabled={!isDirty}>Save {group.title}</Button>
                </div>
            )}

            <ConfirmDialog
                open={confirmOpen}
                title={`Update ${group.title.toLowerCase()}?`}
                variant="danger"
                confirmLabel="Save changes"
                processing={processing}
                onCancel={() => setConfirmOpen(false)}
                onConfirm={() => { setConfirmOpen(false); performSave(); }}
            >
                Changing {group.key === 'security' ? 'security settings' : group.key === 'payment' ? 'payment credentials' : 'AI/RAG credentials'} can affect platform access or billing behavior for every tenant. Confirm you want to save these changes.
            </ConfirmDialog>
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
        <form onSubmit={submit} className="rounded-lg border border-slate-200 bg-white p-6 shadow-soft">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold text-slate-900">Test mail delivery</h2>
                    <p className="mt-1 text-xs text-slate-500">Save Email Identity and Mail Delivery first, then send a real test message.</p>
                </div>
                {recentlySuccessful && <Badge tone="brand">Sent</Badge>}
            </div>
            <div className="mt-5 flex flex-col gap-3 sm:flex-row">
                <div className="flex-1">
                    <Input type="email" placeholder="Recipient email address" value={data.recipient} error={!!errors.recipient} onChange={(event) => setData('recipient', event.target.value)} />
                    {errors.recipient && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.recipient}</p>}
                </div>
                <Button type="submit" variant="brand" loading={processing}>Send test email</Button>
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
        <AuthenticatedLayout header={<PageHeader title="General settings" subtitle="Configure platform identity, security, email, mail delivery, payments, AI/RAG, and branding." />}>
            <Head title="General settings" />

            <div className="mx-auto max-w-6xl">
                <div className="mb-6 md:hidden">
                    <Select value={activeKey} onChange={(event) => setActiveKey(event.target.value)}>
                        {groups.map((group) => <option key={group.key} value={group.key}>{group.title}</option>)}
                    </Select>
                </div>

                <div className="grid gap-6 md:grid-cols-[220px_1fr]">
                    <nav className="hidden space-y-0.5 md:block" aria-label="Settings sections">
                        {groups.map((group) => (
                            <button
                                key={group.key}
                                type="button"
                                onClick={() => setActiveKey(group.key)}
                                className={`block w-full rounded-md px-3 py-2 text-left text-sm font-medium transition ${activeKey === group.key ? 'bg-navy-800 text-white' : 'text-slate-600 hover:bg-slate-100'}`}
                            >
                                {group.title}
                            </button>
                        ))}
                    </nav>

                    <div className="space-y-6">
                        {activeGroup ? <GroupForm key={activeGroup.key} group={activeGroup} canUpdate={canUpdate} /> : (
                            <SectionCard><p className="py-8 text-center text-sm text-slate-500">No settings are configured.</p></SectionCard>
                        )}
                        {canUpdate && ['email', 'mail'].includes(activeKey) && <MailTest />}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
