import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function InvitationAccept({ invitation }) {
    const { data, setData, post, processing, errors } = useForm({ name: invitation?.name || '', password: '', password_confirmation: '' });

    const submit = (event) => {
        event.preventDefault();
        post(window.location.pathname + '/accept');
    };

    if (!invitation?.valid) {
        return (
            <GuestLayout>
                <Head title="Invitation" />
                <h1 className="text-xl font-bold tracking-tight text-slate-900">This invitation is no longer valid</h1>
                <p className="mt-2 text-sm text-slate-500">It may have expired, already been used, or been revoked. Ask your workspace administrator to send a new one.</p>
            </GuestLayout>
        );
    }

    return (
        <GuestLayout>
            <Head title="Accept invitation" />

            <h1 className="text-xl font-bold tracking-tight text-slate-900">You've been invited</h1>
            <p className="mt-2 text-sm text-slate-500">Create your account to join this workspace as <span className="font-medium text-slate-700">{invitation.email}</span>.</p>

            {errors.email && <Alert tone="danger" className="mt-5">{errors.email}</Alert>}

            <form onSubmit={submit} className="mt-6 space-y-5">
                <FormField id="name" label="Your name" required error={errors.name}>
                    <Input id="name" value={data.name} error={!!errors.name} autoFocus onChange={(e) => setData('name', e.target.value)} />
                </FormField>
                <FormField id="password" label="Password" required error={errors.password} hint="Minimum 10 characters.">
                    <Input id="password" type="password" value={data.password} error={!!errors.password} autoComplete="new-password" onChange={(e) => setData('password', e.target.value)} />
                </FormField>
                <FormField id="password_confirmation" label="Confirm password" required>
                    <Input id="password_confirmation" type="password" value={data.password_confirmation} autoComplete="new-password" onChange={(e) => setData('password_confirmation', e.target.value)} />
                </FormField>
                <Button type="submit" variant="brand" loading={processing} className="w-full">Join workspace</Button>
            </form>
        </GuestLayout>
    );
}
