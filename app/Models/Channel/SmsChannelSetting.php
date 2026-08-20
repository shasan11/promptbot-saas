<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsChannelSetting extends Model
{
    protected $fillable = ['channel_id', 'from_number'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
