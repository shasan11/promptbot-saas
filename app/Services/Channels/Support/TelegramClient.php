<?php

namespace App\Services\Channels\Support;

use Illuminate\Support\Facades\Http;

class TelegramClient
{
    private const BASE_URL = 'https://api.telegram.org/';

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws ChannelProviderApiException
     */
    public function call(string $botToken, string $method, array $params = []): array
    {
        $response = Http::baseUrl(self::BASE_URL)
            ->asJson()
            ->timeout(15)
            ->post("bot{$botToken}/{$method}", $params);

        $body = $response->json() ?? [];

        if ($response->failed() || ($body['ok'] ?? false) === false) {
            throw new ChannelProviderApiException($body['description'] ?? 'Telegram API request failed.', $response->status());
        }

        return $body;
    }
}
