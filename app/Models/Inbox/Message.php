<?php

namespace App\Models\Inbox;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasPublicUuid;
    protected $fillable = ['conversation_id', 'sender_type', 'sender_id', 'sender_name', 'direction', 'message_type', 'body', 'body_html', 'channel_message_id', 'reply_to_message_id', 'status', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'failure_reason', 'metadata'];
    protected function casts(): array { return ['sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'failed_at' => 'datetime', 'metadata' => 'array']; }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); } public function replyTo(): BelongsTo { return $this->belongsTo(self::class, 'reply_to_message_id'); }
    public function attachments(): HasMany { return $this->hasMany(ConversationAttachment::class); } public function mentions(): HasMany { return $this->hasMany(MessageMention::class); }
}
