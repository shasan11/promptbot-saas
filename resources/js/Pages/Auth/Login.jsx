import ApplicationLogo from '@/Components/ApplicationLogo';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';

export default function Login({ status, canResetPassword, loginRoute = 'login', panelName = 'Super Admin' }) {
    const { tenant } = usePage().props;
    const isTenant = panelName === 'Tenant Admin';
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit = (event) => {
        event.preventDefault();

        post(route(loginRoute), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout wide>
            <Head title={`${panelName} login`} />

            <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft-lg">
                <div className="grid lg:grid-cols-[0.9fr_1.1fr]">
                    <div className="bg-navy-900 p-8 text-white sm:p-10">
                        <span className="flex h-12 w-12 items-center justify-center rounded-lg bg-white/10">
                            <ApplicationLogo className="h-6 w-6 fill-current text-white" />
                        </span>
                        <div className="mt-10">
                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-brand-300">
                                {isTenant ? (tenant?.companyName || 'Tenant workspace') : 'Superadmin console'}
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight">Welcome back</h1>
                            <p className="mt-4 text-sm leading-6 text-slate-300">
                                {isTenant
                                    ? 'Sign in to manage your team, workspace users, and settings.'
                                    : 'Sign in to manage tenants, billing, platform features, and operational controls.'}
                            </p>
                        </div>
                        <div className="mt-10 flex items-start gap-3 rounded-md border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                            <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-brand-300" aria-hidden="true" />
                            <span>
                                {isTenant
                                    ? 'This workspace is only accessible to invited team members.'
                                    : 'Tenant admins keep using their tenant domain. Platform operators use this console.'}
                            </span>
                        </div>
                    </div>

                    <form onSubmit={submit} className="p-8 sm:p-10">
                        <div>
                            <h2 className="text-xl font-bold tracking-tight text-slate-900">Sign in</h2>
                            <p className="mt-1.5 text-sm text-slate-500">Use your admin credentials to continue.</p>
                        </div>

                        {status && <Alert tone="success" className="mt-6">{status}</Alert>}
                        {(errors.email || errors.password) && (
                            <Alert tone="danger" className="mt-6">{errors.email || errors.password}</Alert>
                        )}

                        <div className="mt-6 space-y-4">
                            <FormField id="email" label="Email address" required>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    autoComplete="username"
                                    autoFocus
                                    required
                                    onChange={(event) => setData('email', event.target.value)}
                                />
                            </FormField>

                            <FormField id="password" label="Password" required>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        value={data.password}
                                        autoComplete="current-password"
                                        required
                                        className="pr-16"
                                        onChange={(event) => setData('password', event.target.value)}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((value) => !value)}
                                        className="absolute right-2 top-1/2 -translate-y-1/2 rounded px-2 py-1 text-xs font-semibold text-slate-500 hover:text-slate-800"
                                    >
                                        {showPassword ? 'Hide' : 'Show'}
                                    </button>
                                </div>
                            </FormField>
                        </div>

                        <div className="mt-5 flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <label className="inline-flex items-center gap-2 font-medium text-slate-600">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(event) => setData('remember', event.target.checked)}
                                    className="rounded border-slate-300 text-navy-800 focus:ring-navy-800"
                                />
                                Remember me
                            </label>

                            {canResetPassword && (
                                <Link href={route('password.request')} className="font-semibold text-navy-800 hover:text-brand-700">
                                    Forgot password?
                                </Link>
                            )}
                        </div>

                        <Button type="submit" variant="brand" size="lg" loading={processing} className="mt-6 w-full">
                            {processing ? 'Signing in…' : 'Sign in'}
                        </Button>
                    </form>
                </div>
            </div>
        </GuestLayout>
    );
}
