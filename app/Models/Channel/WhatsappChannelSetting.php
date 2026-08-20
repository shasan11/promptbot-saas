<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappChannelSetting extends Model
{
    protected $fillable = ['channel_id', 'phone_number_id', 'whatsapp_business_account_id', 'display_phone_number'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
