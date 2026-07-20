import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children, wide = false }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-slate-100 px-4 py-8 sm:justify-center">
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-20 fill-current text-gray-500" />
                </Link>
            </div>

            <div className={wide ? 'mt-6 w-full max-w-5xl' : 'mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg'}>
                {children}
            </div>
        </div>
    );
}
