<?php

namespace App\Models\Inbox;

use App\Models\Channel\Channel;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Customer\Company;
use App\Models\Customer\Contact;
use App\Models\Customer\Tag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Enums\Inbox\ControlState;
use App\Models\AI\ConversationInsight;
use App\Models\AI\Suggestion;

class Conversation extends Model
{
    use HasPublicUuid;
    protected $fillable = ['contact_id', 'company_id', 'channel_id', 'team_id', 'assignee_id', 'status', 'control_state', 'control_changed_at', 'ai_failure_count', 'priority', 'subject', 'first_message_at', 'last_message_at', 'first_response_at', 'resolved_at', 'closed_at', 'snoozed_until', 'message_count', 'unread_count', 'external_reference', 'metadata'];
    protected function casts(): array { return ['first_message_at' => 'datetime', 'last_message_at' => 'datetime', 'first_response_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime', 'snoozed_until' => 'datetime', 'control_changed_at' => 'datetime', 'control_state' => ControlState::class, 'metadata' => 'array']; }

    /**
     * The column default alone is not enough: a freshly instantiated model
     * has no idea what the database would have filled in, so
     * `$conversation->control_state` was null on a just-created conversation
     * and every `->allowsAutomatedReply()` call on it fatalled. Declaring it
     * here means a new Conversation is `ai` from the moment it exists in
     * memory, which is also the correct default for a brand-new thread.
     */
    protected $attributes = ['control_state' => 'ai', 'ai_failure_count' => 0];
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); } public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); } public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); } public function messages(): HasMany { return $this->hasMany(Message::class); }
    public function latestMessage(): HasOne { return $this->hasOne(Message::class)->latestOfMany(); }
    public function assignments(): HasMany { return $this->hasMany(ConversationAssignment::class); }
    public function followers(): BelongsToMany { return $this->belongsToMany(User::class, 'conversation_followers')->withPivot('created_at'); }
    public function tags(): MorphToMany { return $this->morphToMany(Tag::class, 'taggable')->withPivot(['assigned_by', 'created_at']); }
    public function aiInsight(): HasOne { return $this->hasOne(ConversationInsight::class); }
    public function aiSuggestions(): HasMany { return $this->hasMany(Suggestion::class); }

    /**
     * The address an outbound reply must be sent to on this conversation's
     * channel — the single source of truth for every sender (human reply,
     * autonomous agent, widget auto-reply).
     *
     * Different channels identify a person completely differently, and the
     * old `email ?: phone` chain silently returned null for three of them:
     *  - messenger/instagram/telegram identify by a platform-scoped id
     *    (PSID / IGSID / chat id) held in `contacts.external_id`;
     *  - a web-chat visitor who was never asked for an email has NEITHER an
     *    email nor a phone, so the chain produced null there too.
     *
     * A null recipient did not fail loudly: `AutonomousReplyService::send()`
     * gates on `recipient_available`, so an autonomous agent deployed on web
     * chat simply never replied and reported nothing. Web chat needs no
     * routable address at all (delivery happens by the widget polling the
     * messages table), so it resolves to a stable non-null sentinel purely so
     * that gate reflects reality.
     */
    public function recipientAddress(): ?string
    {
        $contact = $this->contact;

        if (! $contact) {
            return null;
        }

        return match ($this->channel?->type) {
            'web_chat' => 'web_chat:'.$contact->id,
            'messenger', 'instagram', 'telegram' => $contact->external_id,
            'whatsapp', 'sms' => $contact->phone ?: $contact->external_id,
            'email' => $contact->email,
            default => $contact->email ?: $contact->phone,
        };
    }
}
