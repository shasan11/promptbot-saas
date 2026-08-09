<?php

namespace App\Contracts\Channels;

use App\Models\Channel\Channel;
use App\Services\Channels\Data\OutboundMessage;
use App\Services\Channels\Data\SendResult;

interface ChannelAdapter
{
    public function type(): string;
    public function available(): bool;
    public function capabilities(): array;
    public function validateConfiguration(Channel $channel): array;
    public function send(Channel $channel, OutboundMessage $message): SendResult;
}
