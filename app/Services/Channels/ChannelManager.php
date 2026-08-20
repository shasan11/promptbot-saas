<?php

namespace App\Services\Channels;

use App\Contracts\Channels\ChannelAdapter;
use App\Services\Channels\Adapters\EmailChannelAdapter;
use App\Services\Channels\Adapters\InstagramChannelAdapter;
use App\Services\Channels\Adapters\MessengerChannelAdapter;
use App\Services\Channels\Adapters\SmsChannelAdapter;
use App\Services\Channels\Adapters\TelegramChannelAdapter;
use App\Services\Channels\Adapters\UnavailableChannelAdapter;
use App\Services\Channels\Adapters\WebChatChannelAdapter;
use App\Services\Channels\Adapters\WhatsappChannelAdapter;
use InvalidArgumentException;

class ChannelManager
{
    public function adapter(string $type): ChannelAdapter
    {
        return match ($type) {
            'email' => app(EmailChannelAdapter::class),
            'web_chat' => app(WebChatChannelAdapter::class),
            'whatsapp' => app(WhatsappChannelAdapter::class),
            'messenger' => app(MessengerChannelAdapter::class),
            'instagram' => app(InstagramChannelAdapter::class),
            'telegram' => app(TelegramChannelAdapter::class),
            'sms' => app(SmsChannelAdapter::class),
            'voice' => new UnavailableChannelAdapter($type),
            default => throw new InvalidArgumentException("Unknown channel type [{$type}]."),
        };
    }

    public function catalog(): array
    {
        $labels = ['email' => 'Email', 'web_chat' => 'Web Chat', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'messenger' => 'Messenger', 'instagram' => 'Instagram', 'telegram' => 'Telegram', 'voice' => 'Voice'];
        return collect($labels)->map(fn ($label, $type) => ['type' => $type, 'label' => $label, 'available' => $this->adapter($type)->available(), 'capabilities' => $this->adapter($type)->capabilities()])->values()->all();
    }
}
