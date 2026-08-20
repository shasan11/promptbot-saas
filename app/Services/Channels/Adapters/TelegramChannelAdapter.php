<?php

namespace App\Services\Channels\Adapters;

use App\Contracts\Channels\ChannelAdapter;
use App\Models\Channel\Channel;
use App\Services\Channels\Data\OutboundMessage;
use App\Services\Channels\Data\SendResult;
use App\Services\Channels\Support\ChannelProviderApiException;
use App\Services\Channels\Support\TelegramClient;
use Throwable;

class TelegramChannelAdapter implements ChannelAdapter
{
    public function __construct(private readonly TelegramClient $telegram) {}

    public function type(): string
    {
        return 'telegram';
    }

    public function available(): bool
    {
        return true;
    }

    public function capabilities(): array
    {
        return ['inbound_webhook', 'outbound_text', 'media'];
    }

    public function validateConfiguration(Channel $channel): array
    {
        $secret = $channel->credential?->encrypted_payload ?? [];

        if (empty($secret['bot_token']) || empty($secret['webhook_secret'])) {
            return ['A bot token and webhook secret are required.'];
        }

        return [];
    }

    public function send(Channel $channel, OutboundMessage $message): SendResult
    {
        try {
            $token = $channel->credential?->encrypted_payload['bot_token'] ?? '';

            $response = $message->attachments === []
                ? $this->telegram->call($token, 'sendMessage', ['chat_id' => $message->recipient, 'text' => $message->text])
                : $this->sendMedia($token, $message);

            return SendResult::sent(isset($response['result']['message_id']) ? (string) $response['result']['message_id'] : null);
        } catch (ChannelProviderApiException $exception) {
            report($exception);

            return SendResult::failed("Telegram delivery failed: {$exception->getMessage()}");
        } catch (Throwable $exception) {
            report($exception);

            return SendResult::failed('Telegram delivery failed. Review the channel delivery log.');
        }
    }

    /** @return array<string, mixed> */
    private function sendMedia(string $token, OutboundMessage $message): array
    {
        $attachment = $message->attachments[0];
        $isImage = ($attachment['type'] ?? 'document') === 'image';
        $method = $isImage ? 'sendPhoto' : 'sendDocument';
        $field = $isImage ? 'photo' : 'document';

        return $this->telegram->call($token, $method, [
            'chat_id' => $message->recipient,
            $field => $attachment['url'],
            'caption' => $message->text,
        ]);
    }
}
