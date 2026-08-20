<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramChannelSetting extends Model
{
    protected $fillable = ['channel_id', 'bot_username', 'bot_id'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
