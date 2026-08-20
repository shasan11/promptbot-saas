<?php

namespace App\Services\Channels\Support;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around Meta's Graph API, shared by the WhatsApp, Messenger,
 * and Instagram adapters — they differ only in the path and access token
 * they send, not in how the HTTP call itself is made or fails.
 */
class MetaGraphClient
{
    private const BASE_URL = 'https://graph.facebook.com/v20.0/';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ChannelProviderApiException
     */
    public function post(string $accessToken, string $path, array $payload): array
    {
        $response = Http::baseUrl(self::BASE_URL)
            ->withToken($accessToken)
            ->asJson()
            ->timeout(15)
            ->post($path, $payload);

        if ($response->failed()) {
            throw new ChannelProviderApiException(
                $response->json('error.message') ?? 'Meta Graph API request failed.',
                $response->status(),
            );
        }

        return $response->json() ?? [];
    }
}
