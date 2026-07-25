import { Link, router, usePage } from '@inertiajs/react';

function can(auth, permissionKey) {
    return auth?.permissions?.includes('*') || auth?.can?.[permissionKey];
}

function NavLink({ href, active, disabled, children }) {
    if (disabled) {
        return (
            <span className="flex cursor-not-allowed items-center rounded-md px-3 py-2 text-sm font-medium text-slate-500">
                {children}
            </span>
        );
    }

    return (
        <Link
            href={href}
            className={`flex items-center rounded-md px-3 py-2 text-sm font-medium transition ${
                active
                    ? 'bg-white text-slate-950 shadow-sm'
                    : 'text-slate-300 hover:bg-slate-900 hover:text-white'
            }`}
        >
            {children}
        </Link>
    );
}

function NavGroup({ title, children }) {
    return (
        <div className="space-y-1">
            <div className="px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{title}</div>
            {children}
        </div>
    );
}

export default function SuperadminLayout({ header, children }) {
    const { auth, flash } = usePage().props;
    const currentRoute = route().current();

    return (
        <div className="min-h-screen bg-slate-50 text-slate-950 lg:flex">
            <aside className="border-b border-slate-800 bg-slate-950 px-4 py-5 lg:fixed lg:inset-y-0 lg:left-0 lg:w-72 lg:overflow-y-auto lg:border-b-0 lg:border-r">
                <div className="flex items-center gap-3 px-2">
                    <div className="grid h-11 w-11 place-items-center rounded-lg bg-white text-sm font-black text-slate-950 shadow-sm">
                        PB
                    </div>
                    <div>
                        <div className="text-base font-bold text-white">PromptBot</div>
                        <div className="text-xs font-medium text-slate-400">Platform Superadmin</div>
                    </div>
                </div>

                <nav className="mt-8 space-y-6">
                    {can(auth, 'viewDashboard') && (
                        <NavGroup title="Overview">
                            <NavLink href={route('superadmin.dashboard')} active={currentRoute === 'superadmin.dashboard'}>
                                Dashboard
                            </NavLink>
                        </NavGroup>
                    )}

                    {can(auth, 'viewTenants') && (
                        <NavGroup title="Tenants">
                            <NavLink href={route('superadmin.tenants.index')} active={currentRoute?.startsWith('superadmin.tenants')}>
                                All Tenants
                            </NavLink>
                            <NavLink disabled>Tenant Health</NavLink>
                        </NavGroup>
                    )}

                    {(can(auth, 'viewPlans') || can(auth, 'viewSubscriptions')) && (
                        <NavGroup title="Billing">
                            {can(auth, 'viewPlans') && (
                                <NavLink href={route('superadmin.billing.plans.index')} active={currentRoute?.startsWith('superadmin.plans') || currentRoute?.startsWith('superadmin.billing.plans')}>
                                    Plans
                                </NavLink>
                            )}
                            {can(auth, 'viewSubscriptions') && (
                                <NavLink href={route('superadmin.billing.subscriptions.index')} active={currentRoute?.startsWith('superadmin.subscriptions') || currentRoute?.startsWith('superadmin.billing.subscriptions')}>
                                    Subscriptions
                                </NavLink>
                            )}
                            {can(auth, 'viewPayments') && (
                                <>
                                    <NavLink href={route('superadmin.billing.resource.index', 'payments')} active={currentRoute === 'superadmin.billing.resource.index' && route().params.resource === 'payments'}>
                                        Payments
                                    </NavLink>
                                    <NavLink href={route('superadmin.billing.resource.index', 'invoices')} active={currentRoute === 'superadmin.billing.resource.index' && route().params.resource === 'invoices'}>
                                        Invoices
                                    </NavLink>
                                    <NavLink href={route('superadmin.billing.resource.index', 'coupons')} active={currentRoute === 'superadmin.billing.resource.index' && route().params.resource === 'coupons'}>
                                        Coupons
                                    </NavLink>
                                    <NavLink href={route('superadmin.billing.resource.index', 'gateways')} active={currentRoute === 'superadmin.billing.resource.index' && route().params.resource === 'gateways'}>
                                        Gateways
                                    </NavLink>
                                </>
                            )}
                        </NavGroup>
                    )}

                    {can(auth, 'viewFeatures') && (
                        <NavGroup title="Platform">
                            <NavLink href={route('superadmin.features.index')} active={currentRoute?.startsWith('superadmin.features')}>
                                Features
                            </NavLink>
                            <NavLink disabled>Feature Flags</NavLink>
                            <NavLink href={route('superadmin.platform.resource.index', 'usage')} active={currentRoute === 'superadmin.platform.resource.index' && route().params.resource === 'usage'}>
                                Usage Metering
                            </NavLink>
                            {can(auth, 'viewIntegrations') && (
                                <NavLink href={route('superadmin.platform.resource.index', 'integrations')} active={currentRoute === 'superadmin.platform.resource.index' && route().params.resource === 'integrations'}>
                                    Integrations and AI
                                </NavLink>
                            )}
                        </NavGroup>
                    )}

                    {can(auth, 'viewWebsite') && (
                        <NavGroup title="Website">
                            <NavLink href={route('superadmin.website.resource.index')} active={currentRoute?.startsWith('superadmin.website')}>
                                Customization
                            </NavLink>
                        </NavGroup>
                    )}

                    {(can(auth, 'viewCommunications') || can(auth, 'viewSupport')) && (
                        <NavGroup title="Customers">
                            {can(auth, 'viewCommunications') && (
                                <NavLink href={route('superadmin.communications.resource.index')} active={currentRoute?.startsWith('superadmin.communications')}>
                                    Communications
                                </NavLink>
                            )}
                            {can(auth, 'viewSupport') && (
                                <NavLink href={route('superadmin.support.index')} active={currentRoute?.startsWith('superadmin.support')}>
                                    Support
                                </NavLink>
                            )}
                        </NavGroup>
                    )}

                    {can(auth, 'viewOperations') && (
                        <NavGroup title="Operations">
                            <NavLink href={route('superadmin.operations.health')} active={currentRoute?.startsWith('superadmin.operations')}>
                                Health and Operations
                            </NavLink>
                        </NavGroup>
                    )}

                    {can(auth, 'viewSettings') && (
                        <NavGroup title="System">
                            <NavLink href={route('superadmin.administrators.index')} active={currentRoute?.startsWith('superadmin.administrators')}>
                                Administrators
                            </NavLink>
                            <NavLink href={route('superadmin.roles.index')} active={currentRoute?.startsWith('superadmin.roles')}>
                                Roles
                            </NavLink>
                            <NavLink href={route('superadmin.settings.edit')} active={currentRoute?.startsWith('superadmin.settings')}>
                                Settings
                            </NavLink>
                            <NavLink href={route('superadmin.security.two-factor')} active={currentRoute?.startsWith('superadmin.security')}>
                                Security
                            </NavLink>
                            {can(auth, 'viewAuditLogs') && (
                                <>
                                    <NavLink href={route('superadmin.audit-logs.index')} active={currentRoute?.startsWith('superadmin.audit-logs')}>
                                        Audit Logs
                                    </NavLink>
                                    <NavLink href={route('superadmin.login-attempts.index')} active={currentRoute?.startsWith('superadmin.login-attempts')}>
                                        Login Attempts
                                    </NavLink>
                                </>
                            )}
                        </NavGroup>
                    )}
                </nav>
            </aside>

            <div className="min-h-screen flex-1 lg:pl-72">
                <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/90 px-4 py-4 backdrop-blur lg:px-8">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="text-sm font-semibold text-slate-900">{auth?.user?.name}</div>
                            <div className="text-xs text-slate-500">{auth?.user?.email}</div>
                        </div>
                        <button
                            className="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50"
                            type="button"
                            onClick={() => router.post(route('logout'))}
                        >
                            Log out
                        </button>
                    </div>
                </header>

                <main className="px-4 py-6 sm:px-6 lg:px-8">
                    {flash?.status && (
                        <div className="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                            {flash.status}
                        </div>
                    )}
                    {header && <div className="mb-6">{header}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}
