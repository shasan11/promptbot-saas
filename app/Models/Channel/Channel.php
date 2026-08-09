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
    protected $fillable = ['type', 'name', 'status', 'configuration', 'team_id', 'default_assignee_id', 'business_hours_policy_id', 'auto_reply_enabled', 'signature', 'created_by', 'last_activity_at'];
    protected function casts(): array { return ['configuration' => 'array', 'auto_reply_enabled' => 'boolean', 'last_activity_at' => 'datetime']; }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function defaultAssignee(): BelongsTo { return $this->belongsTo(User::class, 'default_assignee_id'); }
    public function businessHours(): BelongsTo { return $this->belongsTo(BusinessHourPolicy::class, 'business_hours_policy_id'); }
    public function credential(): HasOne { return $this->hasOne(ChannelCredential::class); }
    public function emailSettings(): HasOne { return $this->hasOne(EmailChannelSetting::class); }
    public function webChatWidget(): HasOne { return $this->hasOne(WebChatWidget::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }
}
