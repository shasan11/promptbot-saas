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

class Conversation extends Model
{
    use HasPublicUuid;
    protected $fillable = ['contact_id', 'company_id', 'channel_id', 'team_id', 'assignee_id', 'status', 'priority', 'subject', 'first_message_at', 'last_message_at', 'first_response_at', 'resolved_at', 'closed_at', 'snoozed_until', 'message_count', 'unread_count', 'external_reference', 'metadata'];
    protected function casts(): array { return ['first_message_at' => 'datetime', 'last_message_at' => 'datetime', 'first_response_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime', 'snoozed_until' => 'datetime', 'metadata' => 'array']; }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); } public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); } public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); } public function messages(): HasMany { return $this->hasMany(Message::class); }
    public function latestMessage(): HasOne { return $this->hasOne(Message::class)->latestOfMany(); }
    public function assignments(): HasMany { return $this->hasMany(ConversationAssignment::class); }
    public function followers(): BelongsToMany { return $this->belongsToMany(User::class, 'conversation_followers')->withPivot('created_at'); }
    public function tags(): MorphToMany { return $this->morphToMany(Tag::class, 'taggable')->withPivot(['assigned_by', 'created_at']); }
}
