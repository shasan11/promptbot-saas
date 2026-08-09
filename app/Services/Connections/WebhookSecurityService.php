<?php

namespace App\Services\Connections;

use App\Models\Connections\WebhookEndpoint;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WebhookSecurityService
{
    private const MAX_PAYLOAD_BYTES = 1048576;
    private const DEFAULT_REPLAY_WINDOW_SECONDS = 300;

    public function validate(Request $request, WebhookEndpoint $endpoint): array
    {
        $payload = $request->getContent();

        if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            throw new InvalidArgumentException('Webhook payload exceeds the configured size limit.');
        }

        $timestamp = $request->header('X-PromptBot-Timestamp') ?? $request->header('X-Timestamp');
        $secret = $endpoint->encrypted_secret;
        $signature = $request->header('X-PromptBot-Signature') ?? $request->header('X-Signature');

        if ($secret) {
            $this->validateTimestamp($timestamp, (int) data_get($endpoint->configuration, 'replay_window_seconds', self::DEFAULT_REPLAY_WINDOW_SECONDS));

            if (! $this->validSignature($payload, (string) $signature, $secret, (string) $timestamp, $endpoint->signature_algorithm ?: 'hmac_sha256')) {
                throw new InvalidArgumentException('Webhook signature verification failed.');
            }
        } elseif ($timestamp) {
            $this->validateTimestamp($timestamp, (int) data_get($endpoint->configuration, 'replay_window_seconds', self::DEFAULT_REPLAY_WINDOW_SECONDS));
        }

        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Webhook payload must be valid JSON.');
        }

        $eventType = (string) ($request->header('X-Event-Type') ?? data_get($decoded, 'type') ?? 'webhook.received');
        $allowedEvents = $endpoint->event_types ?: [];

        if ($allowedEvents !== [] && ! in_array($eventType, $allowedEvents, true)) {
            throw new InvalidArgumentException('Webhook event type is not allowed for this endpoint.');
        }

        return [
            'payload' => $decoded,
            'payload_hash' => hash('sha256', $payload),
            'payload_size' => strlen($payload),
            'signature' => $signature ? '[redacted]' : null,
            'event_type' => $eventType,
            'timestamp' => $timestamp,
        ];
    }

    private function validateTimestamp(?string $timestamp, int $windowSeconds): void
    {
        if (! $timestamp || ! ctype_digit($timestamp)) {
            throw new InvalidArgumentException('Webhook timestamp is required for replay protection.');
        }

        $windowSeconds = max(60, min($windowSeconds, 3600));

        if (abs(now()->timestamp - (int) $timestamp) > $windowSeconds) {
            throw new InvalidArgumentException('Webhook timestamp is outside the replay window.');
        }
    }

    private function validSignature(string $payload, string $signature, string $secret, string $timestamp, string $algorithm): bool
    {
        if ($signature === '') {
            return false;
        }

        if ($algorithm !== 'hmac_sha256') {
            throw new InvalidArgumentException('Webhook signature algorithm is not supported.');
        }

        $base = $timestamp.'.'.$payload;
        $hex = hash_hmac('sha256', $base, $secret);
        $expected = 'sha256='.$hex;

        return hash_equals($expected, $signature) || hash_equals($hex, $signature);
    }
}
