import Button from '@/Components/UI/Button';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Reset password" />

            <h1 className="text-xl font-bold tracking-tight text-slate-900">Choose a new password</h1>
            <p className="mt-2 text-sm text-slate-500">Use at least 8 characters, mixing letters and numbers.</p>

            <form onSubmit={submit} className="mt-6 space-y-5">
                <FormField id="email" label="Email" required error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        autoComplete="username"
                        error={!!errors.email}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                </FormField>

                <FormField id="password" label="New password" required error={errors.password}>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        autoComplete="new-password"
                        autoFocus
                        error={!!errors.password}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                </FormField>

                <FormField id="password_confirmation" label="Confirm new password" required error={errors.password_confirmation}>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        error={!!errors.password_confirmation}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                    />
                </FormField>

                <Button type="submit" variant="brand" loading={processing} className="w-full">
                    {processing ? 'Saving…' : 'Reset password'}
                </Button>
            </form>
        </GuestLayout>
    );
}
