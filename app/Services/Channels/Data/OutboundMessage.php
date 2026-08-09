<?php

namespace App\Services\Channels\Data;

final readonly class OutboundMessage
{
    public function __construct(public string $recipient, public string $subject, public string $text, public ?string $html = null, public array $cc = [], public array $bcc = [], public array $headers = []) {}
}
