<?php

namespace App\Services\Channels\Adapters;

use App\Contracts\Channels\ChannelAdapter;
use App\Models\Channel\Channel;
use App\Services\Channels\Data\OutboundMessage;
use App\Services\Channels\Data\SendResult;
use App\Services\Channels\Support\ChannelProviderApiException;
use App\Services\Channels\Support\MetaGraphClient;
use Throwable;

class WhatsappChannelAdapter implements ChannelAdapter
{
    public function __construct(private readonly MetaGraphClient $graph) {}

    public function type(): string
    {
        return 'whatsapp';
    }

    public function available(): bool
    {
        return true;
    }

    public function capabilities(): array
    {
        return ['inbound_webhook', 'outbound_text', 'media', 'templates'];
    }

    public function validateConfiguration(Channel $channel): array
    {
        $errors = [];

        if (! $channel->whatsappSettings?->phone_number_id) {
            $errors[] = 'A WhatsApp phone number ID is required.';
        }

        $secret = $channel->credential?->encrypted_payload ?? [];

        if (empty($secret['access_token']) || empty($secret['app_secret']) || empty($secret['verify_token'])) {
            $errors[] = 'An access token, app secret, and verify token are required.';
        }

        return $errors;
    }

    public function send(Channel $channel, OutboundMessage $message): SendResult
    {
        try {
            $settings = $channel->whatsappSettings;
            $token = $channel->credential?->encrypted_payload['access_token'] ?? '';

            $payload = $message->attachments === []
                ? ['messaging_product' => 'whatsapp', 'to' => $message->recipient, 'type' => 'text', 'text' => ['body' => $message->text]]
                : $this->mediaPayload($message);

            $response = $this->graph->post($token, "{$settings->phone_number_id}/messages", $payload);

            return SendResult::sent($response['messages'][0]['id'] ?? null);
        } catch (ChannelProviderApiException $exception) {
            report($exception);

            return SendResult::failed("WhatsApp delivery failed: {$exception->getMessage()}");
        } catch (Throwable $exception) {
            report($exception);

            return SendResult::failed('WhatsApp delivery failed. Review the channel delivery log.');
        }
    }

    /** @return array<string, mixed> */
    private function mediaPayload(OutboundMessage $message): array
    {
        $attachment = $message->attachments[0];
        $type = $attachment['type'] ?? 'document';

        return [
            'messaging_product' => 'whatsapp',
            'to' => $message->recipient,
            'type' => $type,
            $type => ['link' => $attachment['url'], 'caption' => $message->text],
        ];
    }
}
