import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { BarChart3 } from 'lucide-react';

const percent = (value) => (value === null || value === undefined ? '—' : `${value}%`);
const seconds = (value) => {
    if (value === null || value === undefined) return '—';
    if (value < 60) return `${value}s`;
    return `${Math.round(value / 60)}m ${value % 60}s`;
};
const money = (value) => (value === null || value === undefined ? '—' : `$${Number(value).toFixed(4)}`);

function Stat({ label, value, hint, tone = 'neutral' }) {
    const accent = tone === 'good' ? 'text-emerald-700' : tone === 'warn' ? 'text-amber-700' : 'text-slate-900';

    return (
        <article className="rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
            <p className="text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-400">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${accent}`}>{value}</p>
            {hint && <p className="mt-1.5 text-[11px] leading-4 text-slate-500">{hint}</p>}
        </article>
    );
}

function Breakdown({ title, rows, empty }) {
    const max = Math.max(1, ...rows.map((row) => row.total));

    return (
        <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
            <h2 className="text-sm font-bold text-slate-900">{title}</h2>
            {rows.length ? (
                <div className="mt-4 space-y-3">
                    {rows.map((row) => (
                        <div key={row.reason || row.label}>
                            <div className="flex justify-between text-sm text-slate-700">
                                <span>{row.label}</span>
                                <Badge tone="neutral">{row.total}</Badge>
                            </div>
                            <div className="mt-1 h-2 rounded bg-slate-100">
                                <div className="h-2 rounded bg-brand-500" style={{ width: `${(row.total / max) * 100}%` }} />
                            </div>
                        </div>
                    ))}
                </div>
            ) : <p className="mt-4 text-xs text-slate-500">{empty}</p>}
        </section>
    );
}

export default function BotPerformance({ metrics }) {
    const { range, volume, deflection, handoff, no_answer: noAnswer, csat, latency, cost, gaps } = metrics;

    const apply = (event) => {
        event.preventDefault();
        router.get(route('tenant.admin.reports.bot-performance'), Object.fromEntries(new FormData(event.currentTarget)), { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            title="Bot performance"
            header={(
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold">Bot performance</h1>
                        <p className="text-sm text-slate-500">How much work the assistant handled, and where it handed over.</p>
                    </div>
                    <Button variant="secondary" href={route('tenant.admin.reports.index')}>Support reporting</Button>
                </div>
            )}
        >
            <Head title="Bot performance" />

            <form className="mb-5 flex flex-wrap gap-3" onSubmit={apply}>
                <input type="date" name="from" defaultValue={range.from} className="rounded border px-3 py-2 text-sm" />
                <input type="date" name="to" defaultValue={range.to} className="rounded border px-3 py-2 text-sm" />
                <Button type="submit">Apply</Button>
            </form>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    label="Deflection"
                    value={percent(deflection.rate)}
                    tone="good"
                    hint={`${deflection.count} of ${volume.total} conversations were answered by the bot with no teammate replying.`}
                />
                <Stat
                    label="Handoff"
                    value={percent(handoff.rate)}
                    tone="warn"
                    hint={`${volume.handed_off} conversations were escalated or taken over.`}
                />
                <Stat
                    label="Could not answer"
                    value={percent(noAnswer.rate)}
                    hint={`${noAnswer.unanswered} of ${noAnswer.questions} customer questions found nothing usable. ${noAnswer.conversations_affected} conversations saw an "I don't know".`}
                />
                <Stat
                    label="CSAT"
                    value={csat.average === null ? '—' : csat.average.toFixed(2)}
                    hint={csat.responses ? `${csat.responses} rating(s), ${percent(csat.positive_rate)} scored 4 or 5.` : 'No ratings submitted in this period.'}
                />
                <Stat
                    label="Answer wait (p50)"
                    value={seconds(latency.p50_seconds)}
                    hint={`From the customer's message to the bot's reply, across ${latency.samples} replies.`}
                />
                <Stat label="Answer wait (p95)" value={seconds(latency.p95_seconds)} hint="The slow tail — what your least patient customer experiences." />
                <Stat
                    label="Cost per conversation"
                    value={money(cost.per_conversation)}
                    hint={`${money(cost.total)} across ${cost.runs} model call(s), covering both the agent and web chat.`}
                />
                <Stat
                    label="Never answered"
                    value={volume.untouched}
                    hint="Conversations where nobody — bot or teammate — replied at all."
                />
            </div>

            <div className="mt-6 grid gap-5 lg:grid-cols-2">
                <Breakdown title="Why conversations were handed over" rows={handoff.reasons} empty="No handoffs recorded in this period." />

                <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-soft">
                    <div className="flex items-center gap-2">
                        <BarChart3 className="h-4 w-4 text-brand-600" />
                        <h2 className="text-sm font-bold text-slate-900">Questions your knowledge base cannot answer</h2>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">Highest volume first. Each one is an article waiting to be written.</p>
                    {gaps.length ? (
                        <ul className="mt-4 divide-y divide-slate-100">
                            {gaps.map((gap) => (
                                <li key={gap.uuid} className="flex items-start justify-between gap-3 py-2.5">
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm text-slate-700">{gap.question}</span>
                                        <span className="mt-0.5 block text-[11px] text-slate-400">{gap.origin.replaceAll('_', ' ')}{gap.best_score !== null ? ` · best match ${gap.best_score}` : ''}</span>
                                    </span>
                                    <Badge tone="neutral">{gap.occurrences}×</Badge>
                                </li>
                            ))}
                        </ul>
                    ) : <p className="mt-4 text-xs text-slate-500">No open knowledge gaps — every question so far found an answer.</p>}
                </section>
            </div>

            <p className="mt-6 text-[11px] leading-5 text-slate-400">
                Conversations are counted in the period they started, so a thread opened on the last day of the range is included even if it was answered later.
                Deflection means no teammate replied — it is a workload measure, not a satisfaction one; read it next to CSAT.
            </p>
        </AuthenticatedLayout>
    );
}
