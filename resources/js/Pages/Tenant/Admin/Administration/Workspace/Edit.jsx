import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { FieldControl, ImageField } from '@/Components/UI/SettingsFields';
import AdministrationShell from '@/Components/Tenant/Administration/AdministrationShell';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ group, groups, title, description, fields, canUpdate }) {
    const imageFields = fields.filter((f) => f.type === 'image');
    const initial = Object.fromEntries([
        ...fields.filter((f) => f.type !== 'image').map((f) => [f.key, f.value ?? '']),
        ...imageFields.map((f) => [f.key, null]),
        ...imageFields.map((f) => [`remove_${f.key}`, false]),
    ]);
    const { data, setData, post, transform, processing, errors, recentlySuccessful, isDirty } = useForm(initial);

    const submit = (event) => {
        event.preventDefault();
        if (!canUpdate) return;
        transform((formData) => ({ ...formData, _method: 'put' }));
        post(route('tenant.admin.administration.workspace.update', group), {
            forceFormData: true,
            preserveScroll: true,
            preserveState: false,
        });
    };

    return (
        <AdministrationShell title="Workspace settings" description="General identity, branding, and localization for this workspace.">
            <Head title={`Workspace · ${title}`} />

            <div className="flex gap-1 overflow-x-auto border-b border-slate-200">
                {groups.map((g) => (
                    <Link
                        key={g.key}
                        href={route('tenant.admin.administration.workspace.edit', g.key)}
                        className={`whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition ${g.key === group ? 'border-brand-600 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
                    >
                        {g.title}
                    </Link>
                ))}
            </div>

            <form onSubmit={submit} className="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-soft">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-bold text-slate-900">{title}</h2>
                        <p className="mt-1 max-w-2xl text-sm text-slate-500">{description}</p>
                    </div>
                    {recentlySuccessful && <Badge tone="brand">Saved</Badge>}
                    {!canUpdate && <Badge tone="neutral">Read only</Badge>}
                </div>

                <div className="grid gap-5 md:grid-cols-2">
                    {fields.map((field) => (
                        <label key={field.key} className={field.type === 'textarea' || field.type === 'image' ? 'block md:col-span-2' : 'block'}>
                            <span className="text-sm font-semibold text-slate-700">{field.label}</span>

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
                                <div className="mt-2"><FieldControl field={field} value={data[field.key]} disabled={!canUpdate} onChange={(value) => setData(field.key, value)} /></div>
                            )}

                            {field.help && <p className="mt-1 text-xs text-slate-500">{field.help}</p>}
                            {errors[field.key] && <p className="mt-1 text-xs font-semibold text-rose-600">{errors[field.key]}</p>}
                        </label>
                    ))}
                </div>

                {canUpdate && (
                    <div className="mt-6 flex items-center justify-end gap-3">
                        {isDirty && <span className="mr-auto text-xs font-medium text-amber-700">Unsaved changes</span>}
                        <Button type="submit" variant="brand" loading={processing} disabled={!isDirty}>Save {title.toLowerCase()}</Button>
                    </div>
                )}
            </form>
        </AdministrationShell>
    );
}
