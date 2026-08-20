<?php

namespace App\Services\Channels\Adapters;

use App\Contracts\Channels\ChannelAdapter;
use App\Models\Channel\Channel;
use App\Services\Channels\Data\OutboundMessage;
use App\Services\Channels\Data\SendResult;
use App\Services\Channels\Support\ChannelProviderApiException;
use App\Services\Channels\Support\MetaGraphClient;
use Throwable;

class MessengerChannelAdapter implements ChannelAdapter
{
    public function __construct(private readonly MetaGraphClient $graph) {}

    public function type(): string
    {
        return 'messenger';
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
        $errors = [];

        if (! $channel->messengerSettings?->page_id) {
            $errors[] = 'A Facebook Page ID is required.';
        }

        $secret = $channel->credential?->encrypted_payload ?? [];

        if (empty($secret['page_access_token']) || empty($secret['app_secret']) || empty($secret['verify_token'])) {
            $errors[] = 'A page access token, app secret, and verify token are required.';
        }

        return $errors;
    }

    public function send(Channel $channel, OutboundMessage $message): SendResult
    {
        try {
            $token = $channel->credential?->encrypted_payload['page_access_token'] ?? '';

            $payload = $message->attachments === []
                ? ['recipient' => ['id' => $message->recipient], 'message' => ['text' => $message->text]]
                : ['recipient' => ['id' => $message->recipient], 'message' => ['attachment' => [
                    'type' => $message->attachments[0]['type'] ?? 'file',
                    'payload' => ['url' => $message->attachments[0]['url'], 'is_reusable' => true],
                ]]];

            $response = $this->graph->post($token, 'me/messages', $payload);

            return SendResult::sent($response['message_id'] ?? null);
        } catch (ChannelProviderApiException $exception) {
            report($exception);

            return SendResult::failed("Messenger delivery failed: {$exception->getMessage()}");
        } catch (Throwable $exception) {
            report($exception);

            return SendResult::failed('Messenger delivery failed. Review the channel delivery log.');
        }
    }
}
