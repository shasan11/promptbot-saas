<?php

namespace App\Services\Channels\Adapters;

use App\Contracts\Channels\ChannelAdapter;
use App\Models\Channel\Channel;
use App\Services\Channels\Data\OutboundMessage;
use App\Services\Channels\Data\SendResult;

class WebChatChannelAdapter implements ChannelAdapter
{
    public function type(): string { return 'web_chat'; }
    public function available(): bool { return true; }
    public function capabilities(): array { return ['visitor_sessions', 'realtime_polling', 'attachments', 'pre_chat_form', 'business_hours']; }
    public function validateConfiguration(Channel $channel): array { return $channel->webChatWidget ? [] : ['Widget configuration is required.']; }
    public function send(Channel $channel, OutboundMessage $message): SendResult { return SendResult::sent(); }
}
