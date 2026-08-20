<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramChannelSetting extends Model
{
    protected $fillable = ['channel_id', 'instagram_business_account_id', 'page_id', 'username'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
