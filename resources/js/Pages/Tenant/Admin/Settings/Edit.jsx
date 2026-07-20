import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ tenant, settings }) {
    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        company_name: settings.companyName || tenant.company_name || '',
        sender_name: settings.branding?.sender_name || '',
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('tenant.admin.settings.update'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Tenant Settings</h2>}>
            <Head title="Tenant Settings" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="space-y-5 rounded border border-gray-200 bg-white p-6">
                    <div>
                        <InputLabel htmlFor="company_name" value="Company name" />
                        <TextInput
                            id="company_name"
                            value={data.company_name}
                            className="mt-1 block w-full"
                            onChange={(event) => setData('company_name', event.target.value)}
                        />
                        <InputError message={errors.company_name} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="sender_name" value="Mail sender name" />
                        <TextInput
                            id="sender_name"
                            value={data.sender_name}
                            className="mt-1 block w-full"
                            onChange={(event) => setData('sender_name', event.target.value)}
                        />
                        <InputError message={errors.sender_name} className="mt-2" />
                    </div>

                    <div className="flex items-center gap-4">
                        <PrimaryButton disabled={processing}>Save</PrimaryButton>
                        {recentlySuccessful && <span className="text-sm text-gray-600">Saved.</span>}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
