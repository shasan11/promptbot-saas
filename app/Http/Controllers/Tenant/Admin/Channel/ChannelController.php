<?php

namespace App\Http\Controllers\Tenant\Admin\Channel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Channel\ChannelRequest;
use App\Models\BusinessHourPolicy;
use App\Models\Channel\BotProfile;
use App\Models\Channel\Channel;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Team;
use App\Models\User;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\Support\ChannelProviderApiException;
use App\Services\Channels\Support\TelegramClient;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function __construct(private readonly ChannelManager $manager, private readonly TenantAuditLogService $audit, private readonly TelegramClient $telegram) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Channel::class);
        $filters = $request->validate(['type' => ['nullable', 'in:'.implode(',', Channel::TYPES)], 'status' => ['nullable', 'in:draft,active,disabled']]);
        return Inertia::render('Tenant/Admin/Channels/Index', [
            'channels' => Channel::query()->with(['team:id,name', 'defaultAssignee:id,name', 'emailSettings', 'webChatWidget'])->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest()->paginate(20)->withQueryString(),
            'catalog' => $this->manager->catalog(), 'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Channel::class);
        $type = $request->validate(['type' => ['nullable', 'in:'.implode(',', Channel::TYPES)]])['type'] ?? 'email';
        return Inertia::render('Tenant/Admin/Channels/Form', array_merge($this->formData(), ['channel' => null, 'selectedType' => $type]));
    }

    public function store(ChannelRequest $request): RedirectResponse
    {
        $adapter = $this->manager->adapter($request->string('type')->toString());
        if (! $adapter->available()) return back()->withErrors(['type' => 'This provider adapter is not installed. The extension point is available, but the channel cannot be activated.']);
        $channel = DB::transaction(fn () => $this->persist($request));
        return redirect()->route('tenant.admin.channels.show', $channel)->with('status', 'Channel created.');
    }

    public function show(Channel $channel): Response
    {
        Gate::authorize('view', $channel); $channel->load(['team:id,name', 'defaultAssignee:id,name', 'businessHours:id,name', 'emailSettings', 'webChatWidget', 'whatsappSettings', 'messengerSettings', 'instagramSettings', 'telegramSettings', 'smsSettings', 'credential:id,channel_id,provider,status,last_rotated_at']);
        return Inertia::render('Tenant/Admin/Channels/Show', ['channel' => $channel, 'adapter' => ['available' => $this->manager->adapter($channel->type)->available(), 'capabilities' => $this->manager->adapter($channel->type)->capabilities(), 'configurationErrors' => $this->manager->adapter($channel->type)->validateConfiguration($channel)], 'embedUrl' => $channel->type === 'web_chat' && $channel->status === 'active' && $channel->webChatWidget?->active ? url('/widget/promptbot.js').'?key='.$channel->webChatWidget->public_key : null, 'inboundWebhookUrl' => match ($channel->type) {
            'email' => route('tenant.channels.email.inbound', $channel),
            'whatsapp' => route('tenant.channels.whatsapp.webhook', $channel),
            'messenger' => route('tenant.channels.messenger.webhook', $channel),
            'instagram' => route('tenant.channels.instagram.webhook', $channel),
            'sms' => route('tenant.channels.sms.webhook', $channel),
            default => null,
        }]);
    }

    public function edit(Channel $channel): Response
    {
        Gate::authorize('update', $channel); $channel->load(['emailSettings', 'webChatWidget', 'whatsappSettings', 'messengerSettings', 'instagramSettings', 'telegramSettings', 'smsSettings', 'credential']);
        return Inertia::render('Tenant/Admin/Channels/Form', array_merge($this->formData(), ['channel' => $channel->makeHidden('credential'), 'selectedType' => $channel->type, 'hasCredentials' => (bool) $channel->credential]));
    }

    public function update(ChannelRequest $request, Channel $channel): RedirectResponse
    {
        Gate::authorize('update', $channel); DB::transaction(fn () => $this->persist($request, $channel));
        return redirect()->route('tenant.admin.channels.show', $channel)->with('status', 'Channel updated.');
    }

    public function destroy(Channel $channel): RedirectResponse
    {
        Gate::authorize('delete', $channel); $channel->update(['status' => 'disabled']); $channel->delete();
        $this->audit->record('channel.deleted', description: "Removed channel \"{$channel->name}\"", subject: $channel);
        return redirect()->route('tenant.admin.channels.index')->with('status', 'Channel removed.');
    }

    public function rotateInboundSecret(Request $request, Channel $channel): RedirectResponse
    {
        Gate::authorize('update', $channel); abort_unless($request->user('tenant')->can('channels.credentials.manage'), 403);
        $secret = Str::random(64); $payload = $channel->credential?->encrypted_payload ?? []; $payload['inbound_secret'] = $secret;
        $channel->credential()->updateOrCreate([], ['provider' => $channel->emailSettings?->outgoing_provider === 'smtp' ? 'smtp' : 'inbound_webhook', 'encrypted_payload' => $payload, 'status' => 'active', 'last_rotated_at' => now(), 'rotated_by' => $request->user('tenant')->id]);
        return back()->with('status', 'Inbound signing secret rotated. Copy it now; it will not be shown again.')->with('channel_secret', $secret);
    }

    private function persist(ChannelRequest $request, ?Channel $channel = null): Channel
    {
        $data = $request->validated(); $channel ??= new Channel(['created_by' => $request->user('tenant')->id]);
        $channel->fill(Arr::only($data, ['type', 'name', 'status', 'team_id', 'default_assignee_id', 'business_hours_policy_id', 'bot_profile_id', 'auto_reply_enabled', 'signature'])); $channel->save();
        if ($channel->type === 'email') {
            $email = $data['email']; $channel->emailSettings()->updateOrCreate([], array_merge(['capture_cc' => true, 'strip_quoted_replies' => true], $email));
            $hadCredential = (bool) $channel->credential;
            $existingSecret = $channel->credential?->encrypted_payload['inbound_secret'] ?? Str::random(64);
            $submittedCredentials = ! empty($data['credentials']['host']) || ! empty($data['credentials']['username']) || ! empty($data['credentials']['password']);
            if (($email['outgoing_provider'] ?? null) === 'smtp' && ! $submittedCredentials && ! $hadCredential) {
                throw \Illuminate\Validation\ValidationException::withMessages(['credentials.host' => 'SMTP credentials are required for a new SMTP channel.']);
            }
            if (($email['outgoing_provider'] ?? null) === 'smtp' && $submittedCredentials) {
                $channel->credential()->updateOrCreate([], ['provider' => 'smtp', 'encrypted_payload' => array_merge($data['credentials'], ['inbound_secret' => $existingSecret]), 'status' => 'active', 'last_rotated_at' => now(), 'rotated_by' => $request->user('tenant')->id]);
            } elseif (! $channel->credential) {
                $channel->credential()->create(['provider' => 'inbound_webhook', 'encrypted_payload' => ['inbound_secret' => $existingSecret], 'status' => 'active', 'last_rotated_at' => now(), 'rotated_by' => $request->user('tenant')->id]);
            }
            if (! $hadCredential) $request->session()->flash('channel_secret', $existingSecret);
        }
        if ($channel->type === 'web_chat') {
            $channel->webChatWidget()->updateOrCreate([], array_merge(['public_key' => $channel->webChatWidget?->public_key ?? Str::random(48)], $data['widget']));
        }
        if (in_array($channel->type, ['whatsapp', 'messenger', 'instagram', 'telegram', 'sms'], true)) {
            $this->persistExternalChannel($channel, $data, $request->user('tenant')->id);
        }
        $errors = $this->manager->adapter($channel->type)->validateConfiguration($channel->fresh(['emailSettings', 'webChatWidget', 'whatsappSettings', 'messengerSettings', 'instagramSettings', 'telegramSettings', 'smsSettings', 'credential']));
        if ($channel->status === 'active' && $errors !== []) throw \Illuminate\Validation\ValidationException::withMessages(['status' => $errors]);
        $this->audit->record($channel->wasRecentlyCreated ? 'channel.created' : 'channel.updated', description: ($channel->wasRecentlyCreated ? 'Created' : 'Updated')." channel \"{$channel->name}\"", subject: $channel, newValues: Arr::except($data, ['credentials']));
        return $channel;
    }

    /**
     * Persists the settings + credential for every non-email/web_chat
     * channel type that connects to an external provider. They all share the
     * same shape — a non-secret settings row plus a credential holding
     * whatever secret fields that provider needs — so one method covers
     * WhatsApp/Messenger/Instagram/Telegram/SMS instead of five near-identical
     * blocks. The only per-type differences are which settings relation
     * applies, the credential's `provider` label, and which field names its
     * required-secret error should point at.
     *
     * @param  array<string, mixed>  $data
     */
    private function persistExternalChannel(Channel $channel, array $data, int $actorId): void
    {
        $settingsRelation = match ($channel->type) {
            'whatsapp' => 'whatsappSettings',
            'messenger' => 'messengerSettings',
            'instagram' => 'instagramSettings',
            'telegram' => 'telegramSettings',
            'sms' => 'smsSettings',
        };

        $channel->{$settingsRelation}()->updateOrCreate([], $data[$channel->type] ?? []);

        $credentials = $data['credentials'] ?? [];
        $submitted = array_filter($credentials, fn ($value) => $value !== null && $value !== '');

        // Editing unrelated fields (e.g. renaming the channel) must not wipe
        // a previously-saved token just because the form resubmits blank
        // credential fields — same guard the email/SMTP branch above uses.
        if ($submitted === [] && $channel->credential) {
            return;
        }

        if ($submitted === [] && ! $channel->credential) {
            $tokenField = match ($channel->type) {
                'whatsapp' => 'access_token',
                'messenger', 'instagram' => 'page_access_token',
                'telegram' => 'bot_token',
                'sms' => 'account_sid',
            };

            throw \Illuminate\Validation\ValidationException::withMessages([
                "credentials.{$tokenField}" => 'The required credential fields for this channel are missing.',
            ]);
        }

        $provider = match ($channel->type) {
            'whatsapp' => 'whatsapp_cloud_api',
            'messenger' => 'meta_messenger',
            'instagram' => 'meta_instagram',
            'telegram' => 'telegram_bot_api',
            'sms' => 'twilio',
        };

        $channel->credential()->updateOrCreate([], [
            'provider' => $provider,
            'encrypted_payload' => array_merge($channel->credential?->encrypted_payload ?? [], $credentials),
            'status' => 'active',
            'last_rotated_at' => now(),
            'rotated_by' => $actorId,
        ]);

        if ($channel->type === 'telegram') {
            $this->registerTelegramWebhook($channel);
        }
    }

    /**
     * Telegram is the one provider here that lets us finish setup ourselves:
     * `setWebhook` just needs the bot token, no OAuth console flow. A bad
     * token must not block saving the channel — it should read the same as
     * any other "not configured yet" state, discoverable via the adapter's
     * validateConfiguration errors on the Show page rather than a hard save
     * failure.
     */
    private function registerTelegramWebhook(Channel $channel): void
    {
        $secret = $channel->credential?->encrypted_payload ?? [];
        $token = $secret['bot_token'] ?? null;
        $webhookSecret = $secret['webhook_secret'] ?? null;

        if (! $token || ! $webhookSecret) {
            return;
        }

        try {
            $this->telegram->call($token, 'setWebhook', [
                'url' => route('tenant.channels.telegram.webhook', $channel),
                'secret_token' => $webhookSecret,
            ]);

            $me = $this->telegram->call($token, 'getMe');

            $channel->telegramSettings()->updateOrCreate([], [
                'bot_username' => $me['result']['username'] ?? null,
                'bot_id' => isset($me['result']['id']) ? (string) $me['result']['id'] : null,
            ]);
        } catch (ChannelProviderApiException $exception) {
            report($exception);
        }
    }

    private function formData(): array
    {
        return ['catalog' => $this->manager->catalog(), 'teams' => Team::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']), 'users' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']), 'businessHours' => BusinessHourPolicy::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']), 'hasCredentials' => false, 'knowledgeBases' => KnowledgeBase::query()->orderBy('name')->get(['id', 'name']), 'botProfiles' => BotProfile::query()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default'])];
    }
}
