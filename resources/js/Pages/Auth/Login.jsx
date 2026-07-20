import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword, loginRoute = 'login', panelName = 'Super Admin' }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();

        post(route(loginRoute), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout wide>
            <Head title={`${panelName} Login`} />

            <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                <div className="grid lg:grid-cols-[0.9fr_1.1fr]">
                    <div className="bg-slate-950 p-8 text-white sm:p-10">
                        <div className="grid h-12 w-12 place-items-center rounded-lg bg-white text-sm font-black text-slate-950">PB</div>
                        <div className="mt-10">
                            <p className="text-sm font-semibold uppercase tracking-[0.2em] text-blue-200">{panelName}</p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight">Welcome back</h1>
                            <p className="mt-4 text-sm leading-6 text-slate-300">
                                Sign in to manage tenants, billing, platform features, and operational controls.
                            </p>
                        </div>
                        <div className="mt-10 rounded-md border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                            Tenant admins keep using their tenant domain. Platform operators use the superadmin panel.
                        </div>
                    </div>

                    <form onSubmit={submit} className="p-8 sm:p-10">
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight text-slate-950">Sign in</h2>
                            <p className="mt-2 text-sm text-slate-500">Use your admin credentials to continue.</p>
                        </div>

                        {status && (
                            <div className="mt-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {status}
                            </div>
                        )}

                        {(errors.email || errors.password) && (
                            <div className="mt-6 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                                {errors.email || errors.password}
                            </div>
                        )}

                        <div className="mt-8 space-y-5">
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Email address</span>
                                <input
                                    type="email"
                                    value={data.email}
                                    autoComplete="username"
                                    autoFocus
                                    onChange={(event) => setData('email', event.target.value)}
                                    className="mt-2 w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950"
                                />
                            </label>

                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Password</span>
                                <input
                                    type="password"
                                    value={data.password}
                                    autoComplete="current-password"
                                    onChange={(event) => setData('password', event.target.value)}
                                    className="mt-2 w-full rounded-md border-slate-300 px-3 py-2.5 text-sm shadow-sm transition focus:border-slate-950 focus:ring-slate-950"
                                />
                            </label>
                        </div>

                        <div className="mt-6 flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <label className="inline-flex items-center gap-2 font-medium text-slate-600">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(event) => setData('remember', event.target.checked)}
                                    className="rounded border-slate-300 text-slate-950 focus:ring-slate-950"
                                />
                                Remember me
                            </label>

                            {canResetPassword && (
                                <Link href={route('password.request')} className="font-semibold text-slate-950 hover:text-blue-700">
                                    Forgot password?
                                </Link>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-8 inline-flex w-full items-center justify-center rounded-md bg-slate-950 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing ? 'Signing in...' : 'Sign in'}
                        </button>
                    </form>
                </div>
            </div>
        </GuestLayout>
    );
}
