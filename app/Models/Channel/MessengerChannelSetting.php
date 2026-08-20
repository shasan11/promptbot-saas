<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerChannelSetting extends Model
{
    protected $fillable = ['channel_id', 'page_id', 'page_name'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
