import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot password" />

            <h1 className="text-xl font-bold tracking-tight text-slate-900">Forgot your password?</h1>
            <p className="mt-2 text-sm text-slate-500">
                Enter the email address on your account and we'll send you a link to choose a new password.
            </p>

            {status && <Alert tone="success" className="mt-5">{status}</Alert>}

            <form onSubmit={submit} className="mt-6 space-y-5">
                <FormField id="email" label="Email address" required error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        autoFocus
                        required
                        error={!!errors.email}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                </FormField>

                <Button type="submit" variant="brand" loading={processing} className="w-full">
                    {processing ? 'Sending link…' : 'Send password reset link'}
                </Button>
            </form>

            <Link href={route('login')} className="mt-6 flex items-center gap-1.5 text-sm font-semibold text-navy-800 hover:text-brand-700">
                <ArrowLeft className="h-4 w-4" /> Back to login
            </Link>
        </GuestLayout>
    );
}
