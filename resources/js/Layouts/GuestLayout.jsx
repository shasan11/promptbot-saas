import BrandLogo from '@/Components/BrandLogo';
import { usePage } from '@inertiajs/react';

export default function GuestLayout({ children, wide = false }) {
    const theme = usePage().props.websiteTheme || {};
    const themeStyle = {
        '--auth-primary': theme.primaryColor || '#064E3B',
        '--auth-secondary': theme.secondaryColor || '#475569',
        '--auth-accent': theme.accentColor || '#059669',
        '--auth-heading-font': theme.headingFont || 'Manrope',
        '--auth-body-font': theme.bodyFont || 'Inter',
        '--auth-button-radius': theme.buttonRadius || '12px',
        '--auth-card-radius': theme.cardRadius || '16px',
    };

    return (
        <div className="auth-theme relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-slate-50 px-4 py-8" style={themeStyle}>
            <div className="pointer-events-none absolute inset-0 auth-theme-glow" aria-hidden="true" />
            {!wide && (
                <a href="/" target="_top" className="relative z-10 mb-6" aria-label="Go to homepage">
                    <BrandLogo className="h-9 w-auto max-w-44" />
                </a>
            )}

            <div className={wide ? 'relative z-10 w-full max-w-5xl' : 'auth-card relative z-10 w-full max-w-md overflow-hidden border border-slate-200 bg-white p-6 shadow-soft-lg sm:p-8'}>
                {!wide && <span className="absolute inset-x-0 top-0 h-1 auth-theme-bar" aria-hidden="true" />}
                {children}
            </div>
        </div>
    );
}
