<?php

namespace App\Models\Channel;

use App\Models\BusinessHourPolicy;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Inbox\Conversation;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    use HasPublicUuid, SoftDeletes;
    public const TYPES = ['email', 'web_chat', 'whatsapp', 'sms', 'messenger', 'instagram', 'telegram', 'voice'];
    protected $fillable = ['type', 'name', 'status', 'configuration', 'team_id', 'default_assignee_id', 'business_hours_policy_id', 'bot_profile_id', 'auto_reply_enabled', 'signature', 'created_by', 'last_activity_at'];
    protected function casts(): array { return ['configuration' => 'array', 'auto_reply_enabled' => 'boolean', 'last_activity_at' => 'datetime']; }
    public function botProfile(): BelongsTo { return $this->belongsTo(BotProfile::class); }

    /** Never null: a channel without an attached profile behaves on documented defaults. */
    /**
     * Resolution order: the profile attached to this channel, then the
     * workspace default, then the built-in defaults.
     *
     * The middle step is what makes the `is_default` flag mean anything —
     * without it the column was a checkbox nothing read, and a workspace that
     * wanted one behaviour everywhere had to attach the same profile to every
     * channel by hand. A workspace with no default profile is unaffected.
     */
    public function effectiveBotProfile(): BotProfile { return $this->botProfile ?: (BotProfile::workspaceDefault() ?: BotProfile::defaults()); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function defaultAssignee(): BelongsTo { return $this->belongsTo(User::class, 'default_assignee_id'); }
    public function businessHours(): BelongsTo { return $this->belongsTo(BusinessHourPolicy::class, 'business_hours_policy_id'); }
    public function credential(): HasOne { return $this->hasOne(ChannelCredential::class); }
    public function emailSettings(): HasOne { return $this->hasOne(EmailChannelSetting::class); }
    public function webChatWidget(): HasOne { return $this->hasOne(WebChatWidget::class); }
    public function whatsappSettings(): HasOne { return $this->hasOne(WhatsappChannelSetting::class); }
    public function messengerSettings(): HasOne { return $this->hasOne(MessengerChannelSetting::class); }
    public function instagramSettings(): HasOne { return $this->hasOne(InstagramChannelSetting::class); }
    public function telegramSettings(): HasOne { return $this->hasOne(TelegramChannelSetting::class); }
    public function smsSettings(): HasOne { return $this->hasOne(SmsChannelSetting::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }
}
