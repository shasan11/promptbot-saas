import PageHeader from '@/Components/Superadmin/PageHeader';
import Alert from '@/Components/UI/Alert';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import Switch from '@/Components/UI/Switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Features({ features, masterEnabled }) {
    const initial = Object.fromEntries(features.map((feature) => [feature.key, feature.enabled]));
    const { data, setData, put, processing, recentlySuccessful } = useForm(initial);

    const submit = (event) => {
        event.preventDefault();
        put(route('superadmin.ai.features.update'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title="AI Features" subtitle="Enable or disable individual AI-powered capabilities across PromptBot." />}>
            <Head title="AI Features" />

            {!masterEnabled && (
                <Alert tone="warning" title="Master AI switch is off" className="mb-6">
                    These toggles have no effect until AI is enabled in AI Settings.
                </Alert>
            )}

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-4">
                {features.map((feature) => (
                    <Card key={feature.key}>
                        <Switch
                            checked={data[feature.key]}
                            onChange={(value) => setData(feature.key, value)}
                            label={feature.label}
                            description={feature.description}
                        />
                        <div className="mt-2 text-xs text-slate-400">Requires purpose: <span className="font-mono">{feature.required_purpose}</span></div>
                    </Card>
                ))}

                <div className="flex items-center justify-end gap-3">
                    {recentlySuccessful && <Badge tone="brand">Saved</Badge>}
                    <Button type="submit" variant="brand" loading={processing}>Save features</Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
