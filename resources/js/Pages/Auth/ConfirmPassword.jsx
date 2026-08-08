import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirm password" />

            <h1 className="text-xl font-bold tracking-tight text-slate-900">Confirm your password</h1>
            <p className="mt-2 text-sm text-slate-500">
                You're about to access a sensitive area of the platform. Confirm your password to continue.
            </p>

            {errors.password && <Alert tone="danger" className="mt-5">{errors.password}</Alert>}

            <form onSubmit={submit} className="mt-6 space-y-5">
                <FormField id="password" label="Password" required>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        autoFocus
                        error={!!errors.password}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                </FormField>

                <Button type="submit" variant="brand" loading={processing} className="w-full">
                    {processing ? 'Confirming…' : 'Confirm'}
                </Button>
            </form>
        </GuestLayout>
    );
}
