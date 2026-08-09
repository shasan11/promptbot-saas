<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailChannelSetting extends Model
{
    protected $fillable = ['channel_id', 'inbox_address', 'incoming_provider', 'outgoing_provider', 'from_name', 'reply_to_address', 'capture_cc', 'capture_bcc', 'strip_quoted_replies'];
    protected function casts(): array { return ['capture_cc' => 'boolean', 'capture_bcc' => 'boolean', 'strip_quoted_replies' => 'boolean']; }
    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
}
