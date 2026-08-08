import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link } from '@inertiajs/react';
import {
    BookOpen, Building2, CheckCircle2, Headphones, Layers, Lock, MessageSquare,
    Menu, Route as RouteIcon, ShieldCheck, ShoppingCart, Sparkles, Workflow, X,
} from 'lucide-react';
import { useState } from 'react';

const capabilities = [
    { icon: MessageSquare, title: 'Unified conversation history', description: 'Every customer conversation lands in one workspace, searchable and attributable, instead of scattered across tools.' },
    { icon: Workflow, title: 'AI-assisted workflows', description: 'Suggested replies and summaries draw on your own operational knowledge base, reviewed before anything reaches a customer.' },
    { icon: Layers, title: 'Role-based access', description: 'Give agents, supervisors, and administrators exactly the access their role needs — nothing more.' },
    { icon: RouteIcon, title: 'Operational routing', description: 'Route conversations by team, priority, or SLA so nothing sits unassigned in a shared inbox.' },
];

const useCases = [
    { icon: Headphones, title: 'Support teams', description: 'Keep ticket volume, SLA status, and reply quality visible in one queue.' },
    { icon: Building2, title: 'BPOs & service businesses', description: 'Run multiple client workspaces with isolated data and consistent operational controls.' },
    { icon: ShoppingCart, title: 'E-commerce operations', description: 'Handle order questions and post-sale support without losing conversation context.' },
];

function NavLink({ href, children }) {
    return <a href={href} className="rounded px-1 py-1 text-sm font-medium text-slate-600 hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">{children}</a>;
}

function ProductPreview() {
    return (
        <div className="mx-auto mt-14 max-w-4xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-soft-lg">
            <div className="flex items-center gap-1.5 border-b border-slate-100 bg-slate-50 px-4 py-3">
                <span className="h-2.5 w-2.5 rounded-full bg-slate-300" />
                <span className="h-2.5 w-2.5 rounded-full bg-slate-300" />
                <span className="h-2.5 w-2.5 rounded-full bg-slate-300" />
            </div>
            <div className="grid grid-cols-[180px_1fr] text-left">
                <div className="hidden border-r border-slate-100 bg-slate-50 p-4 sm:block">
                    <div className="h-2.5 w-20 rounded bg-slate-200" />
                    <div className="mt-4 space-y-2">
                        {['Inbox', 'Assigned', 'Escalated', 'Resolved'].map((label, index) => (
                            <div key={label} className={`flex items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium ${index === 0 ? 'bg-brand-50 text-brand-800' : 'text-slate-500'}`}>
                                <span className={`h-1.5 w-1.5 rounded-full ${index === 0 ? 'bg-brand-600' : 'bg-slate-300'}`} />
                                {label}
                            </div>
                        ))}
                    </div>
                </div>
                <div className="p-5">
                    <div className="flex items-center justify-between">
                        <div className="h-2.5 w-32 rounded bg-slate-200" />
                        <div className="h-6 w-20 rounded-full bg-brand-50" />
                    </div>
                    <div className="mt-5 space-y-3">
                        <div className="flex gap-2">
                            <div className="h-8 w-8 shrink-0 rounded-full bg-slate-200" />
                            <div className="max-w-[70%] rounded-lg rounded-tl-sm bg-slate-100 px-3 py-2 text-xs text-slate-500">Customer message preview…</div>
                        </div>
                        <div className="flex justify-end gap-2">
                            <div className="max-w-[70%] rounded-lg rounded-tr-sm bg-navy-800 px-3 py-2 text-xs text-white">Agent reply, assisted by workspace knowledge</div>
                            <div className="h-8 w-8 shrink-0 rounded-full bg-navy-800" />
                        </div>
                    </div>
                    <div className="mt-5 rounded-md border border-dashed border-brand-200 bg-brand-50/60 p-3 text-xs text-brand-800">
                        <span className="font-semibold">Suggested reply</span> — drafted from linked knowledge base, awaiting agent review.
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function Welcome({ canLogin }) {
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <>
            <Head title="PromptBot — Controlled AI workspace for customer operations" />
            <div className="min-h-screen bg-white text-slate-700 antialiased">
                <header className="border-b border-slate-200">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                        <a href="/" className="flex items-center gap-2.5 text-navy-900">
                            <span className="flex h-9 w-9 items-center justify-center rounded-md bg-navy-900 text-white">
                                <ApplicationLogo className="h-5 w-5 fill-current" />
                            </span>
                            <span className="text-lg font-bold">PromptBot</span>
                        </a>

                        <nav className="hidden items-center gap-7 md:flex" aria-label="Primary">
                            <NavLink href="#capabilities">Capabilities</NavLink>
                            <NavLink href="#use-cases">Use cases</NavLink>
                            <NavLink href="#security">Security</NavLink>
                        </nav>

                        <div className="hidden items-center gap-3 md:flex">
                            {canLogin && (
                                <Link href={route('login')} className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">
                                    Superadmin login
                                </Link>
                            )}
                            <a href="#final-cta" className="rounded-md bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-soft transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2">
                                Request a workspace
                            </a>
                        </div>

                        <button type="button" onClick={() => setMobileOpen((v) => !v)} aria-expanded={mobileOpen} aria-label="Toggle menu" className="flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 text-slate-600 md:hidden">
                            {mobileOpen ? <X className="h-4 w-4" /> : <Menu className="h-4 w-4" />}
                        </button>
                    </div>

                    {mobileOpen && (
                        <nav className="border-t border-slate-100 px-6 py-4 md:hidden" aria-label="Mobile">
                            <div className="flex flex-col gap-3">
                                <NavLink href="#capabilities">Capabilities</NavLink>
                                <NavLink href="#use-cases">Use cases</NavLink>
                                <NavLink href="#security">Security</NavLink>
                                {canLogin && <Link href={route('login')} className="text-sm font-medium text-slate-600">Superadmin login</Link>}
                            </div>
                        </nav>
                    )}
                </header>

                <main>
                    <section className="relative overflow-hidden bg-gradient-to-b from-navy-50/60 to-white px-6 py-20 text-center sm:py-28">
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-800">
                            <Sparkles className="h-3.5 w-3.5" /> Operational AI, not AI magic
                        </span>
                        <h1 className="mx-auto mt-6 max-w-3xl text-4xl font-bold tracking-tight text-navy-900 sm:text-5xl">
                            Bring customer conversations, operational knowledge, and AI-assisted workflows into one controlled workspace
                        </h1>
                        <p className="mx-auto mt-5 max-w-2xl text-lg text-slate-600">
                            PromptBot gives support teams, BPOs, and service businesses a single operational home for high-volume conversations — with the visibility and access controls a serious B2B platform requires.
                        </p>
                        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <a href="#final-cta" className="w-full rounded-md bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-soft transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 sm:w-auto">
                                Request a workspace
                            </a>
                            <a href="#capabilities" className="w-full rounded-md border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 sm:w-auto">
                                See how it works
                            </a>
                        </div>

                        <ProductPreview />
                    </section>

                    <section id="capabilities" className="border-t border-slate-200 px-6 py-20">
                        <div className="mx-auto max-w-6xl">
                            <div className="max-w-2xl">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-brand-700">Key capabilities</h2>
                                <p className="mt-2 text-3xl font-bold tracking-tight text-navy-900">Built for teams that operate at volume</p>
                            </div>
                            <div className="mt-10 grid gap-6 sm:grid-cols-2">
                                {capabilities.map((item) => (
                                    <div key={item.title} className="rounded-lg border border-slate-200 p-6">
                                        <span className="flex h-10 w-10 items-center justify-center rounded-md bg-navy-900 text-white">
                                            <item.icon className="h-5 w-5" />
                                        </span>
                                        <h3 className="mt-4 text-base font-semibold text-navy-900">{item.title}</h3>
                                        <p className="mt-2 text-sm leading-relaxed text-slate-600">{item.description}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section id="use-cases" className="border-t border-slate-200 bg-slate-50 px-6 py-20">
                        <div className="mx-auto max-w-6xl">
                            <div className="max-w-2xl">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-brand-700">Use cases</h2>
                                <p className="mt-2 text-3xl font-bold tracking-tight text-navy-900">Fits how your team already operates</p>
                            </div>
                            <div className="mt-10 grid gap-6 sm:grid-cols-3">
                                {useCases.map((item) => (
                                    <div key={item.title} className="rounded-lg border border-slate-200 bg-white p-6">
                                        <item.icon className="h-6 w-6 text-brand-600" />
                                        <h3 className="mt-4 text-base font-semibold text-navy-900">{item.title}</h3>
                                        <p className="mt-2 text-sm leading-relaxed text-slate-600">{item.description}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section id="security" className="border-t border-slate-200 px-6 py-20">
                        <div className="mx-auto grid max-w-6xl gap-10 lg:grid-cols-2 lg:items-center">
                            <div>
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-brand-700">Security &amp; reliability</h2>
                                <p className="mt-2 text-3xl font-bold tracking-tight text-navy-900">Operational control, not a black box</p>
                                <p className="mt-4 text-sm leading-relaxed text-slate-600">
                                    Every tenant workspace is isolated with its own data and access controls. Administrators can see exactly who has access to what, and every AI-assisted suggestion is reviewed by a person before it reaches a customer — PromptBot assists your team, it doesn't replace their judgment.
                                </p>
                                <ul className="mt-6 space-y-3 text-sm text-slate-700">
                                    {[
                                        'Isolated tenant workspaces with dedicated access controls',
                                        'Role-based permissions across every workspace action',
                                        'AI suggestions are reviewed, not auto-sent, by default',
                                        'Platform-level audit trail for administrative actions',
                                    ].map((item) => (
                                        <li key={item} className="flex items-start gap-2">
                                            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-brand-600" /> {item}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            <div className="rounded-lg border border-slate-200 bg-navy-900 p-8 text-white">
                                <ShieldCheck className="h-8 w-8 text-brand-400" />
                                <p className="mt-4 text-sm leading-relaxed text-slate-300">
                                    AI transparency matters: suggested replies and summaries are clearly labeled as AI-assisted, sourced from your own knowledge base, and never presented as certain or final.
                                </p>
                                <div className="mt-6 flex items-center gap-2 text-xs font-medium text-slate-400">
                                    <Lock className="h-3.5 w-3.5" /> Tenant data stays scoped to its own workspace
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="final-cta" className="border-t border-slate-200 bg-navy-900 px-6 py-20 text-center">
                        <h2 className="text-3xl font-bold text-white">Bring your team's conversations into one workspace</h2>
                        <p className="mx-auto mt-4 max-w-xl text-sm text-slate-300">
                            PromptBot workspaces are provisioned by our team. Reach out and we'll set up your tenant.
                        </p>
                        <a href="mailto:hello@promptbot.app" className="mt-8 inline-block rounded-md bg-brand-500 px-6 py-3 text-sm font-bold text-white shadow-soft transition hover:bg-brand-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-navy-900">
                            Request a workspace
                        </a>
                    </section>
                </main>

                <footer className="border-t border-slate-200 bg-slate-50">
                    <div className="mx-auto max-w-6xl px-6 py-10">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div className="flex items-center gap-2 text-navy-900">
                                <BookOpen className="h-4 w-4 text-brand-600" />
                                <span className="text-sm font-semibold">PromptBot</span>
                            </div>
                            {canLogin && (
                                <Link href={route('login')} className="text-xs font-medium text-slate-500 hover:text-navy-900">
                                    Superadmin login
                                </Link>
                            )}
                        </div>
                        <p className="mt-6 text-xs text-slate-500">&copy; {new Date().getFullYear()} PromptBot. All rights reserved.</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
