import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import FormField from '@/Components/UI/FormField';
import Input from '@/Components/UI/Input';
import TenantAuthLayout from '@/Layouts/TenantAuthLayout';
import { Head, usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ status }) {
    const tenant = usePage().props.tenant || {};
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', password: '', remember: false });
    const [showPassword, setShowPassword] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        post(route('tenant.login'), { onFinish: () => reset('password') });
    };

    return (
        <TenantAuthLayout>
            <Head title={`Login - ${tenant.companyName || 'Tenant Admin'}`} />

            <div className="text-center">
                <h1 className="text-xl font-bold tracking-tight text-slate-950">Sign in</h1>
                <p className="mt-1.5 text-sm text-slate-500">Enter your details to continue.</p>
            </div>

            {status && <Alert tone="success" className="mt-5">{status}</Alert>}
            {(errors.email || errors.password) && <Alert tone="danger" className="mt-5">{errors.email || errors.password}</Alert>}

            <form onSubmit={submit} className="mt-6 space-y-4">
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

                <label className="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" checked={data.remember} onChange={(event) => setData('remember', event.target.checked)} className="rounded border-slate-300 text-brand-600 focus:ring-brand-600" />
                    Remember me
                </label>

                <Button type="submit" variant="brand" size="lg" loading={processing} className="w-full">{processing ? 'Signing in…' : 'Sign in'}</Button>
            </form>
        </TenantAuthLayout>
    );
}
