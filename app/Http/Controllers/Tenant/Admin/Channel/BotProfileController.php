<?php

namespace App\Http\Controllers\Tenant\Admin\Channel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Channel\BotProfileRequest;
use App\Models\Channel\BotProfile;
use App\Models\Team;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for bot behaviour profiles.
 *
 * The table existed with no way to reach it, which meant every workspace ran
 * on `BotProfile::defaults()` whether that suited them or not — the tone,
 * length and escalation thresholds were configurable in the schema and
 * hardcoded in practice.
 */
class BotProfileController extends Controller
{
    public function __construct(private readonly TenantAuditLogService $audit) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', BotProfile::class);

        return Inertia::render('Tenant/Admin/BotProfiles/Index', [
            'profiles' => BotProfile::query()
                ->with('escalationTeam:id,name')
                // The channel count is the answer to "is this profile
                // actually doing anything?", which is the first thing anyone
                // asks when looking at a list of them.
                ->withCount('channels')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->paginate(20),
            'defaults' => $this->defaultsPayload(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', BotProfile::class);

        return Inertia::render('Tenant/Admin/BotProfiles/Form', array_merge($this->formData(), ['profile' => null]));
    }

    public function store(BotProfileRequest $request): RedirectResponse
    {
        Gate::authorize('create', BotProfile::class);

        $profile = DB::transaction(fn () => $this->persist($request, new BotProfile(['created_by' => $request->user('tenant')->id])));

        $this->audit->record('bot_profile.created', description: "Created bot profile \"{$profile->name}\"", subject: $profile);

        return redirect()->route('tenant.admin.bot-profiles.index')->with('status', 'Bot profile created.');
    }

    public function edit(BotProfile $botProfile): Response
    {
        Gate::authorize('update', $botProfile);

        return Inertia::render('Tenant/Admin/BotProfiles/Form', array_merge($this->formData(), [
            'profile' => $botProfile->loadCount('channels'),
        ]));
    }

    public function update(BotProfileRequest $request, BotProfile $botProfile): RedirectResponse
    {
        Gate::authorize('update', $botProfile);

        $before = $botProfile->only(array_keys($request->validated()));
        DB::transaction(fn () => $this->persist($request, $botProfile));

        $this->audit->record(
            'bot_profile.updated',
            description: "Updated bot profile \"{$botProfile->name}\"",
            subject: $botProfile,
            oldValues: $before,
            newValues: $request->validated(),
        );

        return redirect()->route('tenant.admin.bot-profiles.index')->with('status', 'Bot profile updated.');
    }

    public function destroy(BotProfile $botProfile): RedirectResponse
    {
        Gate::authorize('delete', $botProfile);

        // The foreign key is nullOnDelete, so attached channels fall back to
        // the documented defaults rather than breaking — but silently changing
        // several channels' behaviour is not something to do without saying so.
        $attached = $botProfile->channels()->count();
        $name = $botProfile->name;
        $botProfile->delete();

        $this->audit->record('bot_profile.deleted', description: "Deleted bot profile \"{$name}\"", subject: null, subjectLabel: $name, metadata: ['channels_detached' => $attached]);

        return redirect()->route('tenant.admin.bot-profiles.index')->with(
            'status',
            $attached > 0
                ? "Bot profile removed. {$attached} channel(s) reverted to the default behaviour."
                : 'Bot profile removed.',
        );
    }

    private function persist(BotProfileRequest $request, BotProfile $profile): BotProfile
    {
        $data = $request->validated();
        $profile->fill($data)->save();

        // Exactly one default, enforced here rather than by a unique index:
        // MySQL would treat every `false` as a distinct value and a partial
        // index is not available, so the constraint has to live in the write
        // path that owns it.
        if ($profile->is_default) {
            BotProfile::query()->whereKeyNot($profile->id)->where('is_default', true)->update(['is_default' => false]);
        }

        return $profile;
    }

    private function formData(): array
    {
        return [
            'teams' => Team::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'defaults' => $this->defaultsPayload(),
        ];
    }

    /**
     * The behaviour a channel gets with no profile attached — shown in the UI
     * so "no profile" is a visible, understood configuration rather than a
     * blank.
     */
    private function defaultsPayload(): array
    {
        $defaults = BotProfile::defaults();

        return [
            'tone' => $defaults->tone,
            'response_length' => $defaults->response_length,
            'language_policy' => $defaults->language_policy,
            'default_language' => $defaults->default_language,
            'escalate_on_request' => $defaults->escalate_on_request,
            'escalate_after_failures' => $defaults->escalate_after_failures,
            'escalate_on_negative_sentiment' => $defaults->escalate_on_negative_sentiment,
            'escalate_on_risk_flags' => $defaults->escalate_on_risk_flags,
        ];
    }
}
