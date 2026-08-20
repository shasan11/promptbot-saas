<?php

namespace App\Services\Channels\Adapters;

use App\Contracts\Channels\ChannelAdapter;
use App\Models\Channel\Channel;
use App\Services\Channels\Data\OutboundMessage;
use App\Services\Channels\Data\SendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class SmsChannelAdapter implements ChannelAdapter
{
    public function type(): string
    {
        return 'sms';
    }

    public function available(): bool
    {
        return true;
    }

    public function capabilities(): array
    {
        return ['inbound_webhook', 'outbound_text'];
    }

    public function validateConfiguration(Channel $channel): array
    {
        $errors = [];

        if (! $channel->smsSettings?->from_number) {
            $errors[] = 'A From number is required.';
        }

        $secret = $channel->credential?->encrypted_payload ?? [];

        if (empty($secret['account_sid']) || empty($secret['auth_token'])) {
            $errors[] = 'A Twilio Account SID and Auth Token are required.';
        }

        return $errors;
    }

    public function send(Channel $channel, OutboundMessage $message): SendResult
    {
        try {
            $secret = $channel->credential?->encrypted_payload ?? [];
            $sid = $secret['account_sid'] ?? '';
            $token = $secret['auth_token'] ?? '';

            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $message->recipient,
                    'From' => $channel->smsSettings?->from_number,
                    'Body' => $message->text,
                ]);

            if ($response->failed()) {
                return SendResult::failed('SMS delivery failed: '.($response->json('message') ?? 'Twilio request failed.'));
            }

            return SendResult::sent($response->json('sid'));
        } catch (Throwable $exception) {
            report($exception);

            return SendResult::failed('SMS delivery failed. Review the channel delivery log.');
        }
    }
}
