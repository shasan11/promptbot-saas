import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children, wide = false }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-navy-50 px-4 py-10">
            {!wide && (
                <Link href="/" className="mb-6 flex items-center gap-2.5">
                    <span className="flex h-9 w-9 items-center justify-center rounded-md bg-navy-900 text-white">
                        <ApplicationLogo className="h-5 w-5 fill-current" />
                    </span>
                    <span className="text-sm font-semibold text-navy-900">PromptBot</span>
                </Link>
            )}

            <div className={wide ? 'w-full max-w-5xl' : 'w-full max-w-md rounded-lg border border-slate-200 bg-white p-8 shadow-soft-lg'}>
                {children}
            </div>
        </div>
    );
}
