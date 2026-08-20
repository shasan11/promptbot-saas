<?php

namespace App\Services\Channels\Support;

use RuntimeException;

/** Thrown by any external channel provider client (Meta Graph, Telegram Bot API, Twilio) on a failed API call. */
class ChannelProviderApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode)
    {
        parent::__construct($message);
    }
}
