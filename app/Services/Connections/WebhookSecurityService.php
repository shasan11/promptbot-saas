<?php

namespace App\Services\Connections;

use App\Models\Connections\WebhookEndpoint;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WebhookSecurityService
{
    public function validate(Request $request, WebhookEndpoint $endpoint): array
    {
        $payload = $request->getContent();

        if (strlen($payload) > 1024 * 1024) {
            throw new InvalidArgumentException('Webhook payload exceeds the configured size limit.');
        }

        $timestamp = $request->header('X-PromptBot-Timestamp') ?? $request->header('X-Timestamp');
        if ($timestamp && abs(now()->timestamp - (int) $timestamp) > 300) {
            throw new InvalidArgumentException('Webhook timestamp is outside the replay window.');
        }

        $secret = $endpoint->encrypted_secret;
        $signature = $request->header('X-PromptBot-Signature') ?? $request->header('X-Signature');

        if ($secret && ! $this->validSignature($payload, (string) $signature, $secret, $timestamp)) {
            throw new InvalidArgumentException('Webhook signature verification failed.');
        }

        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Webhook payload must be valid JSON.');
        }

        return [
            'payload' => $decoded,
            'payload_hash' => hash('sha256', $payload),
            'payload_size' => strlen($payload),
            'signature' => $signature ? '[redacted]' : null,
        ];
    }

    private function validSignature(string $payload, string $signature, string $secret, ?string $timestamp): bool
    {
        if ($signature === '') {
            return false;
        }

        $base = $timestamp ? $timestamp.'.'.$payload : $payload;
        $expected = 'sha256='.hash_hmac('sha256', $base, $secret);

        return hash_equals($expected, $signature) || hash_equals(hash_hmac('sha256', $base, $secret), $signature);
    }
}
