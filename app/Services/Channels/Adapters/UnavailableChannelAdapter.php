<?php

namespace App\Services\Channels\Adapters;

use App\Contracts\Channels\ChannelAdapter;
use App\Models\Channel\Channel;
use App\Services\Channels\Data\OutboundMessage;
use App\Services\Channels\Data\SendResult;

class UnavailableChannelAdapter implements ChannelAdapter
{
    public function __construct(private readonly string $channelType) {}
    public function type(): string { return $this->channelType; }
    public function available(): bool { return false; }
    public function capabilities(): array { return []; }
    public function validateConfiguration(Channel $channel): array { return ['This channel requires a provider adapter that is not included in this release.']; }
    public function send(Channel $channel, OutboundMessage $message): SendResult { return SendResult::failed('No provider adapter is installed for this channel type.'); }
}
