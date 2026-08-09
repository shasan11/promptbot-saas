<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebChatWidget extends Model
{
    protected $fillable = ['channel_id', 'public_key', 'widget_name', 'primary_color', 'launcher_position', 'welcome_message', 'offline_message', 'pre_chat_fields', 'supported_languages', 'allowed_origins', 'privacy_url', 'terms_url', 'allow_attachments', 'require_email', 'require_name', 'active'];
    protected function casts(): array { return ['pre_chat_fields' => 'array', 'supported_languages' => 'array', 'allowed_origins' => 'array', 'allow_attachments' => 'boolean', 'require_email' => 'boolean', 'require_name' => 'boolean', 'active' => 'boolean']; }
    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
}
