import GoogleButton from '@/Components/Auth/GoogleButton';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ status, canResetPassword, loginRoute = 'login', passwordRequestRoute = 'password.request', panelName = 'Super Admin', googleAuth = null }) {
    const isPortal = panelName === 'Customer Portal';
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', password: '', remember: false });
    const [showPassword, setShowPassword] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        post(route(loginRoute), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title={`${panelName} login`} />
            <form onSubmit={submit}>
                <div className="text-center">
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">{panelName}</p>
                    <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-950">Sign in</h1>
                    <p className="mt-2 text-sm text-slate-500">Enter your details to continue.</p>
                </div>

                {status && <Alert tone="success" className="mt-6">{status}</Alert>}
                {(errors.email || errors.password) && <Alert tone="danger" className="mt-6">{errors.email || errors.password}</Alert>}

                {isPortal && googleAuth?.enabled && (
                    <div className="mt-6">
                        <GoogleButton href={googleAuth.url} />
                        <div className="my-5 flex items-center gap-3" aria-hidden="true"><span className="h-px flex-1 bg-slate-200" /><span className="text-xs text-slate-400">or</span><span className="h-px flex-1 bg-slate-200" /></div>
                    </div>
                )}

                <div className={`${isPortal && googleAuth?.enabled ? '' : 'mt-6'} space-y-4`}>
                    <FormField id="email" label="Email" required>
                        <Input id="email" type="email" value={data.email} autoComplete="username" autoFocus required onChange={(event) => setData('email', event.target.value)} />
                    </FormField>
                    <FormField id="password" label="Password" required>
                        <div className="relative">
                            <Input id="password" type={showPassword ? 'text' : 'password'} value={data.password} autoComplete="current-password" required className="pr-16" onChange={(event) => setData('password', event.target.value)} />
                            <button type="button" onClick={() => setShowPassword((value) => !value)} className="absolute right-2 top-1/2 -translate-y-1/2 rounded px-2 py-1 text-xs font-semibold text-slate-500 hover:text-slate-900">
                                {showPassword ? 'Hide' : 'Show'}
                            </button>
                        </div>
                    </FormField>
                </div>

                <div className="mt-4 flex items-center justify-between gap-3 text-sm">
                    <label className="inline-flex items-center gap-2 text-slate-600"><input type="checkbox" checked={data.remember} onChange={(event) => setData('remember', event.target.checked)} className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600" />Remember me</label>
                    {canResetPassword && <Link href={route(passwordRequestRoute)} className="font-semibold text-indigo-700 hover:text-indigo-500">Forgot password?</Link>}
                </div>

                <Button type="submit" variant="brand" size="lg" loading={processing} className="mt-6 w-full">{processing ? 'Signing in…' : 'Sign in'}</Button>
                {isPortal && <p className="mt-5 text-center text-sm text-slate-500">No account? <Link href={route('portal.register')} className="font-semibold text-indigo-700 hover:text-indigo-500">Sign up</Link></p>}
            </form>
        </GuestLayout>
    );
}
