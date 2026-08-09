<?php

namespace App\Services\Channels\Adapters;

use App\Contracts\Channels\ChannelAdapter;
use App\Models\Channel\Channel;
use App\Services\Channels\Data\OutboundMessage;
use App\Services\Channels\Data\SendResult;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class EmailChannelAdapter implements ChannelAdapter
{
    public function type(): string { return 'email'; }
    public function available(): bool { return true; }
    public function capabilities(): array { return ['inbound_webhook', 'outbound_email', 'html', 'cc', 'bcc', 'attachments', 'threading_headers']; }

    public function validateConfiguration(Channel $channel): array
    {
        $errors = [];
        if (! $channel->emailSettings?->inbox_address) $errors[] = 'An inbox address is required.';
        if ($channel->emailSettings?->outgoing_provider === 'smtp') {
            $secret = $channel->credential?->encrypted_payload ?? [];
            if (empty($secret['host']) || empty($secret['username']) || empty($secret['password'])) $errors[] = 'Complete SMTP credentials are required.';
        }
        return $errors;
    }

    public function send(Channel $channel, OutboundMessage $message): SendResult
    {
        try {
            $settings = $channel->emailSettings;
            $email = (new Email())->from(new Address($settings->inbox_address, $settings->from_name ?: $channel->name))->to($message->recipient)->subject($message->subject)->text($message->text);
            if ($message->html) $email->html($message->html);
            foreach ($message->cc as $address) $email->addCc($address);
            foreach ($message->bcc as $address) $email->addBcc($address);
            foreach ($message->headers as $name => $value) $email->getHeaders()->addTextHeader($name, $value);

            if ($settings->outgoing_provider === 'smtp') {
                $secret = $channel->credential?->encrypted_payload ?? [];
                $scheme = ($secret['encryption'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp';
                $auth = rawurlencode($secret['username'] ?? '').':'.rawurlencode($secret['password'] ?? '').'@';
                $dsn = "{$scheme}://{$auth}{$secret['host']}:".($secret['port'] ?? 587);
                (new Mailer(Transport::fromDsn($dsn)))->send($email);
            } else {
                (new Mailer(Mail::mailer()->getSymfonyTransport()))->send($email);
            }
            return SendResult::sent($email->getHeaders()->get('Message-ID')?->getBodyAsString());
        } catch (Throwable $exception) {
            report($exception);
            return SendResult::failed('Email delivery failed. Review the channel delivery log and mail configuration.');
        }
    }
}
