<?php

namespace App\Models\Channel;

use App\Models\Knowledge\KnowledgeBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebChatWidget extends Model
{
    protected $fillable = ['channel_id', 'public_key', 'widget_name', 'primary_color', 'launcher_position', 'welcome_message', 'suggested_questions', 'offline_message', 'no_answer_message', 'handoff_on_no_answer', 'pre_chat_fields', 'supported_languages', 'allowed_origins', 'privacy_url', 'terms_url', 'allow_attachments', 'require_email', 'require_name', 'active', 'ai_auto_reply_enabled', 'knowledge_base_id'];
    protected function casts(): array { return ['pre_chat_fields' => 'array', 'suggested_questions' => 'array', 'supported_languages' => 'array', 'allowed_origins' => 'array', 'allow_attachments' => 'boolean', 'require_email' => 'boolean', 'require_name' => 'boolean', 'active' => 'boolean', 'ai_auto_reply_enabled' => 'boolean', 'handoff_on_no_answer' => 'boolean']; }

    public const DEFAULT_NO_ANSWER_MESSAGE = "I couldn't find a confident answer to that in our help material. I've flagged this for a teammate who can help.";
    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
    public function knowledgeBase(): BelongsTo { return $this->belongsTo(KnowledgeBase::class); }
}
