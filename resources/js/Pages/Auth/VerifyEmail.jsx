import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function VerifyEmail({ status, sendRoute = 'verification.send', logoutRoute = 'logout' }) {
    const { auth } = usePage().props;
    const { post, processing } = useForm({});

    const submit = (event) => {
        event.preventDefault();

        post(route(sendRoute));
    };

    return (
        <GuestLayout>
            <Head title="Verify email" />

            <h1 className="text-xl font-bold tracking-tight text-slate-900">Verify your email address</h1>
            <p className="mt-2 text-sm text-slate-500">
                We sent a verification link to <span className="font-semibold text-slate-700">{auth?.user?.email}</span>. Click the link to
                activate your account. Didn't get it? We can send another.
            </p>

            {status === 'verification-link-sent' && (
                <Alert tone="success" className="mt-5">A new verification link has been sent to your email address.</Alert>
            )}

            <form onSubmit={submit} className="mt-6 flex items-center justify-between gap-4">
                <Button type="submit" variant="brand" loading={processing}>
                    {processing ? 'Sending…' : 'Resend verification email'}
                </Button>

                <Link
                    href={route(logoutRoute)}
                    method="post"
                    as="button"
                    className="text-sm font-medium text-slate-500 hover:text-slate-800"
                >
                    Log out
                </Link>
            </form>
        </GuestLayout>
    );
}
