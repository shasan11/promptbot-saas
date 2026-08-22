import Pagination from '@/Components/Superadmin/Pagination';
import EngagementShell from '@/Components/Tenant/Engagement/EngagementShell';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { Head, Link, usePage } from '@inertiajs/react';
import { Bot, ChevronRight, Plus } from 'lucide-react';

const toneLabel = { professional: 'Professional', friendly: 'Friendly', casual: 'Casual' };
const lengthLabel = { short: 'Short answers', balanced: 'Balanced answers', detailed: 'Detailed answers' };

function escalationSummary(profile) {
    const rules = [];
    if (profile.escalate_on_request) rules.push('on request');
    if (profile.escalate_after_failures > 0) rules.push(`after ${profile.escalate_after_failures} unanswered`);
    if (profile.escalate_on_negative_sentiment) rules.push('when unhappy');
    if (profile.escalate_on_risk_flags) rules.push('on risk flags');

    return rules.length ? `Hands off ${rules.join(', ')}` : 'Never hands off automatically';
}

export default function Index({ profiles, defaults }) {
    const permissions = usePage().props.auth?.permissions || [];

    return (
        <EngagementShell title="Bot profiles" flush>
            <Head title="Bot profiles" />

            <div className="flex flex-col gap-2.5 border-b border-slate-200 px-3 py-3 sm:flex-row sm:items-center sm:px-4">
                <div className="flex-1">
                    <p className="text-xs font-semibold text-slate-600">How your bots behave</p>
                    <p className="mt-0.5 text-[11px] text-slate-400">Tone, answer length and when a conversation is handed to a teammate. Wording customers see stays on each channel.</p>
                </div>
                {permissions.includes('channels.create') && <Button href={route('tenant.admin.bot-profiles.create')} variant="brand" size="sm" icon={Plus}>New profile</Button>}
            </div>

            <div className="min-h-0 flex-1 p-4 sm:p-5">
                {profiles.data.length ? (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {profiles.data.map((profile) => (
                            <Link
                                key={profile.public_uuid}
                                href={route('tenant.admin.bot-profiles.edit', profile.public_uuid)}
                                className="group rounded-lg border border-slate-200 bg-white p-4 shadow-soft transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-soft-lg"
                            >
                                <div className="flex items-start justify-between">
                                    <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><Bot className="h-4 w-4" /></span>
                                    {profile.is_default && <Badge tone="brand">Default</Badge>}
                                </div>
                                <div className="mt-3 flex items-center gap-2">
                                    <h2 className="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900 group-hover:text-brand-700">{profile.name}</h2>
                                    <ChevronRight className="h-4 w-4 text-slate-300 group-hover:text-brand-500" />
                                </div>
                                <p className="mt-1 text-xs text-slate-500">{toneLabel[profile.tone] || profile.tone} · {lengthLabel[profile.response_length] || profile.response_length}</p>
                                <p className="mt-3 border-t border-slate-100 pt-3 text-[11px] leading-5 text-slate-500">{escalationSummary(profile)}{profile.escalation_team ? ` to ${profile.escalation_team.name}` : ''}.</p>
                                <p className="mt-1 text-[11px] text-slate-400">{profile.channels_count === 1 ? '1 channel uses this' : `${profile.channels_count} channels use this`}</p>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <div className="flex min-h-[360px] items-center justify-center">
                        <EmptyState
                            icon={Bot}
                            title="No bot profiles yet"
                            description={`Every channel currently runs on the built-in defaults: a ${defaults.tone} tone, ${defaults.response_length} answers, and a handoff after ${defaults.escalate_after_failures} unanswered questions. Create a profile to change that.`}
                            action={permissions.includes('channels.create') && <Button href={route('tenant.admin.bot-profiles.create')} variant="brand" icon={Plus}>New profile</Button>}
                        />
                    </div>
                )}
            </div>

            {profiles.data.length > 0 && <div className="border-t border-slate-200 bg-slate-50/30 px-3 py-3 sm:px-4"><Pagination links={profiles.links} /></div>}
        </EngagementShell>
    );
}
