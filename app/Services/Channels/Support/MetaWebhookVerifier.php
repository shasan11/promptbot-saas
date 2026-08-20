<?php

namespace App\Services\Channels\Support;

/**
 * Verifies Meta's `X-Hub-Signature-256` header, shared by the WhatsApp,
 * Messenger, and Instagram inbound webhook controllers — all three products
 * sign their webhook deliveries the same way.
 */
class MetaWebhookVerifier
{
    public function verify(string $rawBody, ?string $signatureHeader, string $appSecret): bool
    {
        if (! $signatureHeader || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $appSecret);
        $given = substr($signatureHeader, strlen('sha256='));

        return hash_equals($expected, $given);
    }
}
