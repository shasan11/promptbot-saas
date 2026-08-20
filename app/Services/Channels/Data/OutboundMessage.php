<?php

namespace App\Services\Channels\Data;

final readonly class OutboundMessage
{
    /** @param  array<int, array{url: string, type: string}>  $attachments  Media links (image/document/video/audio) for the messaging channels — ignored by Email. */
    public function __construct(
        public string $recipient,
        public string $subject,
        public string $text,
        public ?string $html = null,
        public array $cc = [],
        public array $bcc = [],
        public array $headers = [],
        public array $attachments = [],
    ) {}
}
