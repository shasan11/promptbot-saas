import PageHeader from '@/Components/Superadmin/PageHeader';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

function FieldControl({ field, value, onChange }) {
    if (field.type === 'checkbox') {
        return (
            <label className="mt-2 flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700">
                <input type="checkbox" checked={!!value} onChange={(event) => onChange(event.target.checked)} className="rounded border-slate-300" />
                Enabled
            </label>
        );
    }

    return <Input type="number" value={value ?? ''} onChange={(event) => onChange(event.target.value)} />;
}

export default function Settings({ fields }) {
    const initial = Object.fromEntries(fields.map((field) => [field.key, field.value]));
    const { data, setData, put, processing, errors, recentlySuccessful, isDirty } = useForm(initial);

    const submit = (event) => {
        event.preventDefault();
        put(route('superadmin.ai.settings.update'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title="AI Settings" subtitle="Global behavior for timeouts, retries, fallback, logging, and privacy defaults." />}>
            <Head title="AI Settings" />

            <form onSubmit={submit} className="mx-auto max-w-4xl">
                <SectionCard
                    title="Platform AI settings"
                    actions={recentlySuccessful && <Badge tone="brand">Saved</Badge>}
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        {fields.map((field) => (
                            <FormField
                                key={field.key}
                                label={field.label}
                                hint={field.help}
                                error={errors[field.key]}
                                className={field.type === 'checkbox' ? '' : ''}
                            >
                                <FieldControl field={field} value={data[field.key]} onChange={(value) => setData(field.key, value)} />
                            </FormField>
                        ))}
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-3">
                        {isDirty && <span className="mr-auto text-xs font-medium text-amber-700">Unsaved changes</span>}
                        <Button type="submit" variant="brand" loading={processing} disabled={!isDirty}>Save settings</Button>
                    </div>
                </SectionCard>
            </form>
        </AuthenticatedLayout>
    );
}
