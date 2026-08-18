import { usePage } from '@inertiajs/react';

const DEFAULT_TENANT_PRIMARY = '#059669';

function tenantBrandVariables(primaryColor, secondaryColor) {
    const primary = /^#[0-9A-Fa-f]{6}$/.test(primaryColor ?? '') ? primaryColor : DEFAULT_TENANT_PRIMARY;
    const secondary = /^#[0-9A-Fa-f]{6}$/.test(secondaryColor ?? '') ? secondaryColor : '#0F172A';
    const rgb = (hex) => [1, 3, 5].map((offset) => Number.parseInt(hex.slice(offset, offset + 2), 16));
    const mix = (hex, target, amount) => rgb(hex).map((channel, index) => Math.round(channel + (target[index] - channel) * amount)).join(' ');

    return {
        '--brand-50': mix(primary, [255, 255, 255], 0.94),
        '--brand-100': mix(primary, [255, 255, 255], 0.86),
        '--brand-200': mix(primary, [255, 255, 255], 0.72),
        '--brand-300': mix(primary, [255, 255, 255], 0.54),
        '--brand-400': mix(primary, [255, 255, 255], 0.28),
        '--brand-500': mix(primary, [255, 255, 255], 0.12),
        '--brand-600': rgb(primary).join(' '),
        '--brand-700': mix(primary, [0, 0, 0], 0.15),
        '--brand-800': mix(primary, [0, 0, 0], 0.3),
        '--brand-900': mix(primary, [0, 0, 0], 0.45),
        '--focus-ring': primary,
        '--tenant-secondary': secondary,
    };
}

/**
 * Minimal, tenant-branded auth shell — deliberately separate from the shared
 * GuestLayout (used by super admin/customer portal), which is themed off the
 * central platform's website settings and always shows the platform logo.
 * This one reads the tenant's own logo/colors from the `tenant` prop that
 * HandleInertiaRequests shares on every tenant-domain request, guest pages
 * included.
 */
export default function TenantAuthLayout({ children }) {
    const tenant = usePage().props.tenant || {};

    return (
        <div
            className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 py-10"
            style={tenantBrandVariables(tenant.primaryColor, tenant.secondaryColor)}
        >
            <div className="w-full max-w-sm">
                <div className="mb-8 flex justify-center">
                    {tenant.logoUrl ? (
                        <img src={tenant.logoUrl} alt={tenant.companyName || 'Workspace logo'} className="h-9 w-auto max-w-[12rem] object-contain" />
                    ) : (
                        <span className="text-lg font-bold tracking-tight text-slate-900">{tenant.companyName || 'Workspace'}</span>
                    )}
                </div>

                <div className="rounded-xl border border-slate-200 bg-white px-8 py-10 sm:px-10">
                    {children}
                </div>
            </div>
        </div>
    );
}
